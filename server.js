"use strict";

require("dotenv").config();

const crypto = require("node:crypto");
const path = require("node:path");
const express = require("express");
const { MongoClient, ObjectId } = require("mongodb");

const app = express();
const port = Number(process.env.PORT || 3000);
const dbName = process.env.DB_NAME || "emergency_dashboard";
const sessionCookie = "emergency_session";
const sessionDurationSeconds = 12 * 60 * 60;
const publicDirectory = path.join(__dirname, "public");

let client;
let db;
let connecting;

app.disable("x-powered-by");
app.use((req, res, next) => {
  res.setHeader("X-Content-Type-Options", "nosniff");
  res.setHeader("Referrer-Policy", "same-origin");
  res.setHeader("X-Frame-Options", "DENY");
  next();
});
app.use(express.json({ limit: "32kb", strict: true }));
app.use(express.static(publicDirectory, { index: "index.html", fallthrough: true }));

function safeEqual(left, right) {
  const a = Buffer.from(String(left));
  const b = Buffer.from(String(right));
  return a.length > 0 && a.length === b.length && crypto.timingSafeEqual(a, b);
}

function getSessionSecret() {
  return process.env.SESSION_SECRET || "";
}

function signSession(user) {
  const payload = Buffer.from(JSON.stringify({
    id: String(user.id),
    username: user.username,
    expiresAt: Date.now() + sessionDurationSeconds * 1000
  })).toString("base64url");
  const signature = crypto.createHmac("sha256", getSessionSecret()).update(payload).digest("base64url");
  return `${payload}.${signature}`;
}

function readSession(req) {
  const secret = getSessionSecret();
  if (!secret) return null;

  const cookies = String(req.headers.cookie || "").split(";");
  const entry = cookies.map(part => part.trim()).find(part => part.startsWith(`${sessionCookie}=`));
  if (!entry) return null;

  const token = entry.slice(sessionCookie.length + 1);
  const separator = token.lastIndexOf(".");
  if (separator <= 0) return null;

  const payload = token.slice(0, separator);
  const signature = token.slice(separator + 1);
  const expected = crypto.createHmac("sha256", secret).update(payload).digest("base64url");
  if (!safeEqual(signature, expected)) return null;

  try {
    const session = JSON.parse(Buffer.from(payload, "base64url").toString("utf8"));
    return session.expiresAt > Date.now() ? session : null;
  } catch {
    return null;
  }
}

function setSessionCookie(res, user) {
  const secure = process.env.NODE_ENV === "production" ? "; Secure" : "";
  res.setHeader("Set-Cookie", `${sessionCookie}=${signSession(user)}; Path=/; Max-Age=${sessionDurationSeconds}; HttpOnly; SameSite=Lax${secure}`);
}

function clearSessionCookie(res) {
  const secure = process.env.NODE_ENV === "production" ? "; Secure" : "";
  res.setHeader("Set-Cookie", `${sessionCookie}=; Path=/; Max-Age=0; HttpOnly; SameSite=Lax${secure}`);
}

function requireLogin(req, res, next) {
  const user = readSession(req);
  if (!user) {
    res.status(401).json({ success: false, message: "Please sign in to use the emergency dashboard." });
    return;
  }
  req.user = user;
  next();
}

async function connectDatabase() {
  if (db) return db;
  if (connecting) return connecting;

  const uri = process.env.MONGODB_URI || "";
  if (!uri || /YOUR_|PASSWORD|PASTE|PLACEHOLDER/i.test(uri)) {
    throw new Error("MongoDB is not configured. Set a valid MONGODB_URI environment variable.");
  }

  connecting = (async () => {
    client = new MongoClient(uri, { serverSelectionTimeoutMS: 8000 });
    await client.connect();
    db = client.db(dbName);
    return db;
  })();

  try {
    return await connecting;
  } catch (error) {
    if (client) await client.close().catch(() => {});
    client = null;
    throw error;
  } finally {
    connecting = null;
  }
}

async function logActivity(activity) {
  try {
    await db.collection("activity_history").insertOne({
      activity,
      created_at: new Date().toISOString()
    });
  } catch (error) {
    // A failed audit write must not make a completed data mutation look failed.
    console.error("Activity history write failed:", error.message);
  }
}

function route(handler) {
  return async (req, res) => {
    try {
      await connectDatabase();
      await handler(req, res);
    } catch (error) {
      console.error("API request failed:", error.message);
      res.status(500).json({
        success: false,
        message: "The service could not complete the request. Check the server configuration and logs."
      });
    }
  };
}

function mongoId(id) {
  const value = String(id ?? "");
  return ObjectId.isValid(value) && String(new ObjectId(value)) === value
    ? new ObjectId(value)
    : value;
}

function output(document) {
  const { _id, ...rest } = document;
  return { id: String(_id), ...rest };
}

function registerCrud(apiPath, legacyPath, collectionName, label, normalize, options = {}) {
  const collection = () => db.collection(collectionName);

  const getHandler = route(async (req, res) => {
    const documents = await collection().find({}).sort({ _id: -1 }).limit(500).toArray();
    res.json(documents.map(output));
  });
  app.get(apiPath, requireLogin, getHandler);
  app.get(legacyPath, requireLogin, getHandler);

  const postHandler = route(async (req, res) => {
    const body = req.body;
    if (!body || typeof body !== "object" || Array.isArray(body)) {
      res.status(400).json({ success: false, message: "Request body must be a JSON object." });
      return;
    }

    if (options.resource && body.action === "update") {
      const id = String(body.id ?? "");
      const quantity = Number(body.quantity);
      if (!id || !Number.isSafeInteger(quantity) || quantity < 0) {
        res.status(400).json({ success: false, message: "A valid resource ID and non-negative whole quantity are required." });
        return;
      }
      const result = await collection().updateOne({ _id: mongoId(id) }, { $set: { quantity } });
      if (!result.matchedCount) {
        res.status(404).json({ success: false, message: "Resource not found." });
        return;
      }
      await logActivity(`Updated resource quantity (ID ${id})`);
      res.json({ success: true });
      return;
    }

    const document = normalize(body);
    const validation = options.validate ? options.validate(document) : null;
    if (validation) {
      res.status(400).json({ success: false, message: validation });
      return;
    }

    const result = await collection().insertOne({ ...document, created_at: new Date().toISOString() });
    await logActivity(`Added ${label} (ID ${result.insertedId})`);
    res.status(201).json({ success: true, message: `${label} added successfully.`, id: String(result.insertedId) });
  });
  app.post(apiPath, requireLogin, postHandler);
  app.post(legacyPath, requireLogin, postHandler);

  const deleteHandler = route(async (req, res) => {
    const id = String(req.query.id ?? "");
    if (!id) {
      res.status(400).json({ success: false, message: "A valid record ID is required." });
      return;
    }
    const result = await collection().deleteOne({ _id: mongoId(id) });
    if (!result.deletedCount) {
      res.status(404).json({ success: false, message: `${label} not found.` });
      return;
    }
    await logActivity(`Deleted ${label} (ID ${id})`);
    res.json({ success: true, message: `${label} deleted successfully.` });
  });
  app.delete(apiPath, requireLogin, deleteHandler);
  app.delete(legacyPath, requireLogin, deleteHandler);
}

registerCrud("/api/incidents", "/incidents.php", "incidents", "incident", body => ({
  incident_type: String(body.incident_type || "Other").trim(),
  title: String(body.title || "").trim(),
  location: String(body.location || "").trim(),
  priority: String(body.priority || "Medium").trim(),
  status: String(body.status || "Active").trim(),
  response_team: String(body.response_team || "").trim(),
  responder: String(body.responder || body.response_team || "").trim(),
  description: String(body.description || "").trim()
}), { validate: value => !value.title || !value.location ? "Incident title and location are required." : null });

registerCrud("/api/team", "/response_team.php", "response_team", "response team member", body => ({
  name: String(body.name || "").trim(),
  role: String(body.role || "").trim(),
  phone: String(body.phone || "").trim(),
  status: String(body.status || "Available").trim(),
  assigned_incident: ""
}), { validate: value => !value.name || !value.role ? "Team name and role are required." : null });

registerCrud("/api/resources", "/resources.php", "resources", "resource", body => ({
  name: String(body.name || body.resource_name || "").trim(),
  quantity: Number(body.quantity),
  unit: String(body.unit || "units").trim()
}), {
  resource: true,
  validate: value => !value.name || !Number.isSafeInteger(value.quantity) || value.quantity < 0
    ? "Resource name and a non-negative whole quantity are required."
    : null
});

registerCrud("/api/victims", "/victims.php", "victims", "victim", body => ({
  name: String(body.name || "").trim(),
  location: String(body.location || "").trim(),
  condition: String(body.condition || body.condition_status || "Unknown").trim(),
  condition_status: String(body.condition || body.condition_status || "Unknown").trim(),
  rescue_status: String(body.rescue_status || "Pending").trim(),
  notes: String(body.notes || "").trim()
}), { validate: value => !value.name ? "Victim name is required." : null });

const historyHandler = route(async (req, res) => {
  const documents = await db.collection("activity_history").find({}).sort({ _id: -1 }).limit(200).toArray();
  res.json({ success: true, data: documents.map(output) });
});
app.get("/api/history", requireLogin, historyHandler);
app.get("/activity_history.php", requireLogin, historyHandler);

const alertsGetHandler = route(async (req, res) => {
  const documents = await db.collection("alerts").find({}).sort({ _id: -1 }).limit(200).toArray();
  res.json({ success: true, data: documents.map(output) });
});
app.get("/api/alerts", requireLogin, alertsGetHandler);
app.get("/alerts.php", requireLogin, alertsGetHandler);

const alertsPostHandler = route(async (req, res) => {
  const body = req.body || {};
  const type = String(body.type || "Other").trim();
  const priority = String(body.priority || "Medium").trim();
  const location = String(body.location || "").trim();
  const message = String(body.message || "").trim();
  const types = ["Fire", "Road Accident", "Flood", "Medical Emergency", "Building Collapse", "Other"];
  const priorities = ["Critical", "High", "Medium", "Low"];

  if (!types.includes(type) || !priorities.includes(priority) || !location || location.length > 255 || message.length > 2000) {
    res.status(400).json({ success: false, message: "Enter a valid emergency type, priority, location, and message." });
    return;
  }

  const document = { type, priority, location, message, created_at: new Date().toISOString() };
  const result = await db.collection("alerts").insertOne(document);
  await logActivity(`Created ${priority} alert at ${location}`);
  res.status(201).json({ success: true, message: "Emergency alert saved.", id: String(result.insertedId) });
});
app.post("/api/alerts", requireLogin, alertsPostHandler);
app.post("/alerts.php", requireLogin, alertsPostHandler);

app.get("/api/session", (req, res) => {
  const user = readSession(req);
  res.setHeader("Cache-Control", "no-store");
  res.json({ success: true, authenticated: Boolean(user), user: user ? { id: user.id, username: user.username } : null });
});
app.get("/session.php", (req, res) => {
  const user = readSession(req);
  res.setHeader("Cache-Control", "no-store");
  res.json({ success: true, authenticated: Boolean(user), user: user ? { id: user.id, username: user.username } : null });
});

function loginHandler(req, res) {
  const username = typeof req.body?.username === "string" ? req.body.username.trim() : "";
  const password = typeof req.body?.password === "string" ? req.body.password : "";
  const expectedUser = process.env.ADMIN_USERNAME || "";
  const expectedPassword = process.env.ADMIN_PASSWORD || "";
  const secret = getSessionSecret();

  if (!expectedUser || !expectedPassword || !secret) {
    res.status(503).json({ success: false, message: "Login is not configured. Set ADMIN_USERNAME, ADMIN_PASSWORD, and SESSION_SECRET." });
    return;
  }
  if (!safeEqual(username, expectedUser) || !safeEqual(password, expectedPassword)) {
    res.status(401).json({ success: false, message: "Invalid username or password." });
    return;
  }

  const user = { id: "admin", username: expectedUser };
  setSessionCookie(res, user);
  res.setHeader("Cache-Control", "no-store");
  res.json({ success: true, message: "Login successful.", user: { username: expectedUser } });
}
app.post("/api/login", loginHandler);
app.post("/login.php", loginHandler);

function logoutHandler(req, res) {
  clearSessionCookie(res);
  if (req.path.endsWith(".php")) {
    res.redirect(303, "/login.html");
    return;
  }
  res.json({ success: true });
}
app.post("/api/logout", logoutHandler);
app.get("/logout.php", logoutHandler);

app.get("/api/health", requireLogin, route(async (req, res) => {
  await db.command({ ping: 1 });
  res.json({ success: true, message: "MongoDB connected successfully." });
}));

app.use((error, req, res, next) => {
  if (res.headersSent) return next(error);
  const status = error.status === 413 ? 413 : error.status === 400 ? 400 : 500;
  res.status(status).json({
    success: false,
    message: status === 400 ? "Request body must be valid JSON." : status === 413 ? "Request body is too large." : "Unexpected server error."
  });
});

app.use((req, res) => {
  res.status(404).json({ success: false, message: "Route not found." });
});

module.exports = app;

if (require.main === module) {
  connectDatabase().then(() => {
    app.listen(port, () => console.log(`Emergency dashboard running at http://localhost:${port}`));
  }).catch(error => {
    console.error("Server startup failed:", error.message);
    process.exitCode = 1;
  });

  process.on("SIGINT", async () => {
    if (client) await client.close();
    process.exit(0);
  });
}

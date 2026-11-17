
/* =========================================================
   EMERGENCY RESPONSE DASHBOARD
   COMPLETE JAVASCRIPT - UPDATED
========================================================= */

const INCIDENT_API = "incidents.php";
const TEAM_API = "response_team.php";
const RESOURCE_API = "resources.php";
const VICTIM_API = "victims.php";
const HISTORY_API = "activity_history.php";

let incidentsData = [];
let teamData = [];
let resourcesData = [];
let victimsData = [];
let historyData = [];

/* COMMON API FUNCTION */
async function apiRequest(url, options = {}) {
    const response = await fetch(url, options);
    let result;

    try {
        result = await response.json();
    } catch {
        result = {};
    }

    if (!response.ok || result.success === false) {
        throw new Error(
            result.message ||
            "Request failed. HTTP status: " + response.status
        );
    }

    return result;
}

/* GET ARRAY FROM API RESPONSE */
function getArray(result) {
    if (Array.isArray(result)) return result;
    if (Array.isArray(result.data)) return result.data;
    if (Array.isArray(result.records)) return result.records;
    if (Array.isArray(result.results)) return result.results;
    return [];
}

/* ESCAPE HTML */
function escapeHTML(value) {
    if (value === null || value === undefined) return "";

    return String(value)
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}

/* NOTIFICATION */
function showNotification(message, type = "success") {
    let notification = document.getElementById("notification");

    if (!notification) {
        notification = document.createElement("div");
        notification.id = "notification";
        notification.className = "notification";
        document.body.appendChild(notification);
    }

    notification.textContent = message;
    notification.className = "notification show " + type;

    setTimeout(() => {
        notification.classList.remove("show");
    }, 3500);
}

/* STATUS CLASS */
function getStatusClass(status) {
    const value = String(status || "").toLowerCase();

    const classes = {
        available: "status-available",
        active: "status-active",
        assigned: "status-assigned",
        resolved: "status-resolved",
        rescued: "status-rescued",
        busy: "status-busy",
        pending: "status-pending",
        unavailable: "status-unavailable",
        offline: "status-unavailable",
        "on mission": "status-busy",
        "off duty": "status-unavailable"
    };

    return classes[value] || "";
}

/* PRIORITY CLASS */
function getPriorityClass(priority) {
    const value = String(priority || "").toLowerCase();

    const classes = {
        low: "priority-low",
        medium: "priority-medium",
        high: "priority-high",
        critical: "priority-critical"
    };

    return classes[value] || "";
}

/* NAVIGATION */
function setupNavigation() {
    document.querySelectorAll(".nav-menu a").forEach(link => {
        link.addEventListener("click", function(event) {
            const target =
                this.getAttribute("data-section") ||
                this.getAttribute("href");

            if (target && target.startsWith("#")) {
                event.preventDefault();
                showSection(target.substring(1));
            }
        });
    });
}

/* SHOW SECTION */
function showSection(sectionId) {
    document.querySelectorAll(".section").forEach(section => {
        section.classList.remove("active");
    });

    const target = document.getElementById(sectionId);
    if (target) target.classList.add("active");

    document.querySelectorAll(".nav-menu a").forEach(link => {
        const href = link.getAttribute("href");
        const dataSection = link.getAttribute("data-section");

        link.classList.toggle(
            "active",
            href === "#" + sectionId || dataSection === sectionId
        );
    });

    if (sectionId === "map" && window.emergencyMap) {
        setTimeout(() => window.emergencyMap.invalidateSize(), 100);
    }

    window.scrollTo({ top: 0, behavior: "smooth" });
}

/* LOAD INCIDENTS */
async function loadIncidents() {
    try {
        incidentsData = getArray(await apiRequest(INCIDENT_API));
        renderIncidents();
        loadIncidentOptions();
        updateDashboard();
    } catch (error) {
        console.error("Incident error:", error);
        renderIncidentError();
        showNotification("Unable to load incidents.", "error");
    }
}

/* RENDER INCIDENTS */
function renderIncidents(list = incidentsData) {
    const tbody = document.getElementById("incidentTableBody");
    if (!tbody) return;

    if (!list.length) {
        tbody.innerHTML =
            '<tr><td colspan="8" class="empty-row">No incidents found.</td></tr>';
        return;
    }

    tbody.innerHTML = list.map(incident => {
        const id = incident.id ?? incident.incident_id ?? "";
        const title = incident.title ?? incident.incident_title ?? incident.name ?? "";
        const location = incident.location ?? "";
        const priority = incident.priority ?? "Low";
        const status = incident.status ?? "Active";
        const description = incident.description ?? "";

        return `
            <tr>
                <td>${escapeHTML(id)}</td>
                <td><strong>${escapeHTML(title)}</strong></td>
                <td>${escapeHTML(location)}</td>
                <td><span class="priority-badge ${getPriorityClass(priority)}">${escapeHTML(priority)}</span></td>
                <td><span class="status-badge ${getStatusClass(status)}">${escapeHTML(status)}</span></td>
                <td>${escapeHTML(description)}</td>
                <td>${escapeHTML(incident.created_at || "-")}</td>
                <td>
                    <button class="delete-btn" onclick="deleteIncident('${escapeHTML(id)}')">
                        🗑 Delete
                    </button>
                </td>
            </tr>`;
    }).join("");
}

function renderIncidentError() {
    const tbody = document.getElementById("incidentTableBody");
    if (tbody) {
        tbody.innerHTML =
            '<tr><td colspan="8" class="empty-row">Unable to load incidents.</td></tr>';
    }
}

/* ADD INCIDENT */
function setupIncidentForm() {
    const form = document.getElementById("incidentForm");
    if (!form || form.dataset.handlerAttached === "true") return;

    form.dataset.handlerAttached = "true";

    form.addEventListener("submit", async event => {
        event.preventDefault();

        const title = document.getElementById("incidentTitle")?.value.trim() || "";
        const location = document.getElementById("incidentLocation")?.value.trim() || "";
        const priority = document.getElementById("incidentPriority")?.value || "Medium";
        const status = document.getElementById("incidentStatus")?.value || "Active";
        const description = document.getElementById("incidentDescription")?.value.trim() || "";

        if (!title || !location) {
            showNotification("Enter the incident title and location.", "warning");
            return;
        }

        try {
            await apiRequest(INCIDENT_API, {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({ title, location, priority, status, description })
            });

            showNotification("Incident added successfully.", "success");
            form.reset();
            await loadIncidents();
            await loadHistory();
        } catch (error) {
            console.error("Add incident error:", error);
            showNotification(error.message || "Unable to add incident.", "error");
        }
    });
}

/* DELETE INCIDENT */
async function deleteIncident(id) {
    if (!id || !confirm("Are you sure you want to delete this incident?")) return;

    try {
        await apiRequest(INCIDENT_API + "?id=" + encodeURIComponent(id), {
            method: "DELETE"
        });

        showNotification("Incident deleted successfully.", "success");
        await loadIncidents();
        await loadHistory();
    } catch (error) {
        console.error("Delete incident error:", error);
        showNotification(error.message || "Unable to delete incident.", "error");
    }
}

/* LOAD RESPONSE TEAM */
async function loadTeamMembers() {
    const tbody = document.getElementById("teamTableBody");

    try {
        const result = await apiRequest(TEAM_API);
        teamData = getArray(result);
        renderTeamMembers();
        updateDashboard();
    } catch (error) {
        console.error("Team error:", error);

        if (tbody) {
            tbody.innerHTML =
                '<tr><td colspan="7" class="empty-row">Unable to load response team.</td></tr>';
        }

        showNotification("Unable to load response team.", "error");
    }
}

/* RENDER RESPONSE TEAM */
function renderTeamMembers(list = teamData) {
    const tbody = document.getElementById("teamTableBody");

    if (!tbody) {
        console.error('Missing <tbody id="teamTableBody"> in index.html.');
        return;
    }

    if (!list.length) {
        tbody.innerHTML =
            '<tr><td colspan="7" class="empty-row">No response team members found.</td></tr>';
        return;
    }

    tbody.innerHTML = list.map(team => {
        const id = team.id ?? team.team_id ?? "";
        const name = team.name ?? team.member_name ?? "";
        const role = team.role ?? team.position ?? "";
        const phone = team.phone ?? team.mobile ?? "";
        const status = team.status ?? team.availability ?? "Available";
        const assigned =
            team.assigned_incident ||
            team.assignedIncident ||
            team.incident_title ||
            team.incident ||
            "Not Assigned";

        return `
            <tr>
                <td>${escapeHTML(id)}</td>
                <td><strong>${escapeHTML(name)}</strong></td>
                <td>${escapeHTML(role)}</td>
                <td>${escapeHTML(phone)}</td>
                <td><span class="status-badge ${getStatusClass(status)}">${escapeHTML(status)}</span></td>
                <td>${escapeHTML(assigned)}</td>
                <td>
                    <button class="delete-btn" onclick="deleteTeamMember('${escapeHTML(id)}')">
                        🗑 Delete
                    </button>
                </td>
            </tr>`;
    }).join("");
}

/* INCIDENT OPTIONS FOR TEAM FORM */
function loadIncidentOptions() {
    const select = document.getElementById("teamIncident");
    if (!select) return;

    const previousValue = select.value;

    select.innerHTML = '<option value="">-- No Incident Assigned --</option>';

    incidentsData.forEach(incident => {
        const id = incident.id ?? incident.incident_id ?? "";
        if (!id) return;

        const option = document.createElement("option");
        option.value = id;
        option.textContent =
            (incident.title ?? incident.incident_title ?? "Emergency") +
            (incident.location ? " - " + incident.location : "");

        select.appendChild(option);
    });

    if ([...select.options].some(option => option.value === previousValue)) {
        select.value = previousValue;
    }
}

/* ADD RESPONSE TEAM - FIXED */
function setupTeamForm() {
    const form = document.getElementById("teamForm");

    if (!form) {
        console.error('Team form not found. Check for id="teamForm" in index.html.');
        return;
    }

    if (form.dataset.teamHandlerAttached === "true") return;
    form.dataset.teamHandlerAttached = "true";

    form.addEventListener("submit", async event => {
        event.preventDefault();

        const name = document.getElementById("teamName")?.value.trim() || "";
        const role = document.getElementById("teamRole")?.value.trim() || "";
        const phone = document.getElementById("teamPhone")?.value.trim() || "";
        const status = document.getElementById("teamStatus")?.value || "Available";

        if (!name || !role) {
            showNotification("Enter both team name and team role.", "warning");
            return;
        }

        const submitButton = form.querySelector(
            'button[type="submit"], input[type="submit"]'
        );

        if (submitButton) submitButton.disabled = true;

        try {
            const result = await apiRequest(TEAM_API, {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({ name, role, phone, status })
            });

            showNotification(
                result.message || "Response team added successfully.",
                "success"
            );

            form.reset();

            await loadTeamMembers();
            updateDashboard();
            await loadHistory();
        } catch (error) {
            console.error("Add response team error:", error);
            showNotification(error.message || "Unable to add response team.", "error");
        } finally {
            if (submitButton) submitButton.disabled = false;
        }
    });
}

/* DELETE RESPONSE TEAM MEMBER */
async function deleteTeamMember(id) {
    if (!id || !confirm("Are you sure you want to delete this team member?")) return;

    try {
        await apiRequest(TEAM_API + "?id=" + encodeURIComponent(id), {
            method: "DELETE"
        });

        showNotification("Team member deleted successfully.", "success");
        await loadTeamMembers();
        await loadHistory();
    } catch (error) {
        console.error("Delete team error:", error);
        showNotification(error.message || "Unable to delete team member.", "error");
    }
}

/* LOAD VICTIMS */
async function loadVictims() {
    const tbody = document.getElementById("victimTableBody");

    try {
        victimsData = getArray(await apiRequest(VICTIM_API));
        renderVictims();
        updateDashboard();
    } catch (error) {
        console.error("Victim error:", error);

        if (tbody) {
            tbody.innerHTML =
                '<tr><td colspan="7" class="empty-row">Unable to load victims.</td></tr>';
        }
    }
}

/* RENDER VICTIMS */
function renderVictims(list = victimsData) {
    const tbody = document.getElementById("victimTableBody");
    if (!tbody) return;

    if (!list.length) {
        tbody.innerHTML =
            '<tr><td colspan="7" class="empty-row">No victims registered.</td></tr>';
        return;
    }

    tbody.innerHTML = list.map(victim => {
        const id = victim.id ?? victim.victim_id ?? "";
        const name = victim.name ?? victim.victim_name ?? "";
        const location = victim.location ?? "";
        const condition = victim.condition ?? victim.victim_condition ?? "Stable";
        const status = victim.rescue_status ?? victim.status ?? "Pending";

        return `
            <tr>
                <td>${escapeHTML(id)}</td>
                <td><strong>${escapeHTML(name)}</strong></td>
                <td>${escapeHTML(location)}</td>
                <td>${escapeHTML(condition)}</td>
                <td><span class="status-badge ${getStatusClass(status)}">${escapeHTML(status)}</span></td>
                <td>${escapeHTML(victim.created_at || "-")}</td>
                <td>
                    <button class="delete-btn" onclick="deleteVictim('${escapeHTML(id)}')">
                        🗑 Delete
                    </button>
                </td>
            </tr>`;
    }).join("");
}

/* ADD VICTIM */
function setupVictimForm() {
    const form = document.getElementById("victimForm");
    if (!form || form.dataset.handlerAttached === "true") return;

    form.dataset.handlerAttached = "true";

    form.addEventListener("submit", async event => {
        event.preventDefault();

        const name = document.getElementById("victimName")?.value.trim() || "";
        const location = document.getElementById("victimLocation")?.value.trim() || "";
        const condition = document.getElementById("victimCondition")?.value || "Stable";
        const rescueStatus =
            document.getElementById("victimRescueStatus")?.value || "Pending";

        if (!name) {
            showNotification("Please enter the victim name.", "warning");
            return;
        }

        try {
            await apiRequest(VICTIM_API, {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({
                    name,
                    location,
                    condition,
                    rescue_status: rescueStatus
                })
            });

            showNotification("Victim registered successfully.", "success");
            form.reset();
            await loadVictims();
            await loadHistory();
        } catch (error) {
            console.error("Add victim error:", error);
            showNotification(error.message || "Unable to register victim.", "error");
        }
    });
}

/* DELETE VICTIM */
async function deleteVictim(id) {
    if (!id || !confirm("Are you sure you want to delete this victim?")) return;

    try {
        await apiRequest(VICTIM_API + "?id=" + encodeURIComponent(id), {
            method: "DELETE"
        });

        showNotification("Victim deleted successfully.", "success");
        await loadVictims();
        await loadHistory();
    } catch (error) {
        console.error("Delete victim error:", error);
        showNotification(error.message || "Unable to delete victim.", "error");
    }
}

/* LOAD RESOURCES */
async function loadResources() {
    try {
        resourcesData = getArray(await apiRequest(RESOURCE_API));
        renderResources();
        updateDashboard();
    } catch (error) {
        console.error("Resource error:", error);
        showNotification("Unable to load resources.", "error");
    }
}

/* RESOURCE ICON */
function getResourceIcon(name) {
    const value = String(name || "").toLowerCase();

    if (value.includes("ambulance")) return "🚑";
    if (value.includes("medical")) return "🩺";
    if (value.includes("fire")) return "🚒";
    if (value.includes("water")) return "💧";
    if (value.includes("rescue")) return "🛟";

    return "📦";
}

/* RENDER RESOURCES */
function renderResources() {
    const container = document.getElementById("resourceContainer");
    if (!container) return;

    if (!resourcesData.length) {
        container.innerHTML = '<div class="card"><p>No resources found.</p></div>';
        return;
    }

    container.innerHTML = resourcesData.map(resource => {
        const id = resource.id ?? resource.resource_id ?? "";
        const name = resource.name ?? resource.resource_name ?? "";
        const quantity = Number(resource.quantity ?? resource.amount ?? 0);

        return `
            <div class="resource-card">
                <div class="resource-icon">${getResourceIcon(name)}</div>
                <h3>${escapeHTML(name)}</h3>
                <div class="resource-quantity">${quantity}</div>
                <div class="resource-actions">
                    <button onclick="changeResource('${escapeHTML(id)}', 1)" title="Increase">+</button>
                    <button onclick="changeResource('${escapeHTML(id)}', -1)" title="Decrease">−</button>
                    <button onclick="deleteResource('${escapeHTML(id)}')" title="Delete">🗑</button>
                </div>
            </div>`;
    }).join("");
}

/* CHANGE RESOURCE QUANTITY */
async function changeResource(id, amount) {
    if (!id) return;

    try {
        await apiRequest(RESOURCE_API + "?id=" + encodeURIComponent(id), {
            method: "PUT",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ change: amount })
        });

        await loadResources();
    } catch (error) {
        console.error("Resource update error:", error);
        showNotification(error.message || "Unable to update resource.", "error");
    }
}

/* DELETE RESOURCE */
async function deleteResource(id) {
    if (!id || !confirm("Are you sure you want to delete this resource?")) return;

    try {
        await apiRequest(RESOURCE_API + "?id=" + encodeURIComponent(id), {
            method: "DELETE"
        });

        showNotification("Resource deleted successfully.", "success");
        await loadResources();
    } catch (error) {
        console.error("Delete resource error:", error);
        showNotification(error.message || "Unable to delete resource.", "error");
    }
}

/* ADD RESOURCE */
function setupResourceForm() {
    const form = document.getElementById("resourceForm");
    if (!form || form.dataset.handlerAttached === "true") return;

    form.dataset.handlerAttached = "true";

    form.addEventListener("submit", async event => {
        event.preventDefault();

        const name = document.getElementById("resourceName")?.value.trim() || "";
        const quantity = Number(document.getElementById("resourceQuantity")?.value || 0);

        if (!name || quantity < 0) {
            showNotification("Enter a resource name and a valid quantity.", "warning");
            return;
        }

        try {
            await apiRequest(RESOURCE_API, {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({ name, quantity })
            });

            showNotification("Resource added successfully.", "success");
            form.reset();
            await loadResources();
        } catch (error) {
            console.error("Add resource error:", error);
            showNotification(error.message || "Unable to add resource.", "error");
        }
    });
}

/* LOAD HISTORY */
async function loadHistory() {
    const tbody = document.getElementById("historyTableBody");

    try {
        historyData = getArray(await apiRequest(HISTORY_API));
        renderHistory();
    } catch (error) {
        console.error("History error:", error);

        if (tbody) {
            tbody.innerHTML =
                '<tr><td colspan="5" class="empty-row">Unable to load activity history.</td></tr>';
        }
    }
}

/* RENDER HISTORY */
function renderHistory() {
    const tbody = document.getElementById("historyTableBody");
    if (!tbody) return;

    if (!historyData.length) {
        tbody.innerHTML =
            '<tr><td colspan="5" class="empty-row">No activity history found.</td></tr>';
        return;
    }

    tbody.innerHTML = historyData.map(item => {
        const id = item.id ?? "";
        const action = item.action ?? item.activity ?? item.event ?? "Activity";
        const user = item.user ?? item.username ?? item.performed_by ?? "System";
        const date = item.created_at ?? item.timestamp ?? item.date ?? "";
        const description = item.description ?? item.details ?? "";

        return `
            <tr>
                <td>${escapeHTML(id)}</td>
                <td>${escapeHTML(action)}</td>
                <td>${escapeHTML(description)}</td>
                <td>${escapeHTML(user)}</td>
                <td>${escapeHTML(date)}</td>
            </tr>`;
    }).join("");
}

/* UPDATE DASHBOARD */
function updateDashboard() {
    const countStatus = status =>
        incidentsData.filter(item =>
            String(item.status || "").toLowerCase() === status
        ).length;

    const active = countStatus("active");
    const assigned = countStatus("assigned");
    const resolved = countStatus("resolved");

    const availableTeam = teamData.filter(team =>
        String(team.status ?? team.availability ?? "").toLowerCase() === "available"
    ).length;

    const ambulances = resourcesData.reduce((total, resource) => {
        const name = String(resource.name ?? resource.resource_name ?? "").toLowerCase();

        return name.includes("ambulance")
            ? total + Number(resource.quantity ?? resource.amount ?? 0)
            : total;
    }, 0);

    const rescued = victimsData.filter(victim => {
        const status = String(victim.rescue_status ?? victim.status ?? "").toLowerCase();
        return ["rescued", "safe", "completed"].includes(status);
    }).length;

    setNumber(["activeEmergencies", "activeIncidents", "dashboardActiveIncidents"], active);
    setNumber(["assignedIncidents", "dashboardAssignedIncidents"], assigned);
    setNumber(["resolvedIncidents", "dashboardResolvedIncidents"], resolved);
    setNumber(["availableResponders", "dashboardAvailableResponders"], availableTeam);
    setNumber(["ambulanceCount", "availableAmbulances", "dashboardAmbulances"], ambulances);
    setNumber(["peopleRescued", "rescuedPeople", "dashboardPeopleRescued"], rescued);

    setNumber(["reportActive"], active);
    setNumber(["reportResponders"], availableTeam);
    setNumber(["reportAmbulances"], ambulances);
    setNumber(["reportRescued"], rescued);
}

/* SET DASHBOARD NUMBER */
function setNumber(ids, value) {
    ids.forEach(id => {
        const element = document.getElementById(id);
        if (element) element.textContent = value;
    });
}

/* INCIDENT SEARCH AND FILTER */
function setupIncidentSearch() {
    const search = document.getElementById("incidentSearch");
    const priority = document.getElementById("incidentPriorityFilter");
    const status = document.getElementById("incidentStatusFilter");

    if (!search && !priority && !status) return;

    const filterIncidents = () => {
        const searchValue = String(search?.value || "").toLowerCase();
        const priorityValue = String(priority?.value || "").toLowerCase();
        const statusValue = String(status?.value || "").toLowerCase();

        const filtered = incidentsData.filter(incident => {
            const title = String(incident.title ?? incident.incident_title ?? "").toLowerCase();
            const location = String(incident.location ?? "").toLowerCase();
            const incidentPriority = String(incident.priority ?? "").toLowerCase();
            const incidentStatus = String(incident.status ?? "").toLowerCase();

            return (
                (!searchValue || title.includes(searchValue) || location.includes(searchValue)) &&
                (!priorityValue || incidentPriority === priorityValue) &&
                (!statusValue || incidentStatus === statusValue)
            );
        });

        renderIncidents(filtered);
    };

    search?.addEventListener("input", filterIncidents);
    priority?.addEventListener("change", filterIncidents);
    status?.addEventListener("change", filterIncidents);
}

/* EMERGENCY ALERT */
function showEmergencyAlert(message) {
    const alertBox = document.getElementById("emergencyAlert");

    if (!alertBox) {
        showNotification(message, "error");
        return;
    }

    alertBox.className = "alert-box alert-danger";
    alertBox.textContent = "🚨 Emergency Alert: " + message;
    alertBox.style.display = "block";
}

/* CLEAR EMERGENCY ALERT */
function clearEmergencyAlert() {
    const alertBox = document.getElementById("emergencyAlert");
    if (alertBox) alertBox.style.display = "none";
}

/* MAP INITIALIZATION */
function initializeMap() {
    const mapElement =
        document.getElementById("map") ||
        document.getElementById("emergencyMap");

    if (!mapElement) return;

    if (typeof L === "undefined") {
        mapElement.textContent =
            "Map library is not loaded. Add the Leaflet CSS and JavaScript files to index.html.";
        return;
    }

    const map = L.map(mapElement).setView([16.5062, 80.6480], 12);

    L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
        attribution: "&copy; OpenStreetMap contributors"
    }).addTo(map);

    L.marker([16.5062, 80.6480])
        .addTo(map)
        .bindPopup("Emergency Response Center");

    window.emergencyMap = map;
}

/* EXPORT INCIDENTS TO CSV */
function exportIncidentsCSV() {
    if (!incidentsData.length) {
        showNotification("No incident records available.", "warning");
        return;
    }

    const rows = [[
        "ID", "Title", "Location", "Priority", "Status", "Description"
    ]];

    incidentsData.forEach(incident => {
        rows.push([
            incident.id ?? incident.incident_id ?? "",
            incident.title ?? incident.incident_title ?? "",
            incident.location ?? "",
            incident.priority ?? "",
            incident.status ?? "",
            incident.description ?? ""
        ]);
    });

    downloadCSV(rows, "incident_records.csv");
}

/* DOWNLOAD CSV */
function downloadCSV(rows, filename) {
    const csv = rows.map(row =>
        row.map(value =>
            '"' + String(value ?? "").replace(/"/g, '""') + '"'
        ).join(",")
    ).join("\r\n");

    const blob = new Blob(["\uFEFF" + csv], {
        type: "text/csv;charset=utf-8;"
    });

    const url = URL.createObjectURL(blob);
    const link = document.createElement("a");

    link.href = url;
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    link.remove();

    setTimeout(() => URL.revokeObjectURL(url), 1000);
}

/* PRINT REPORT */
function printReport() {
    window.print();
}

/* REFRESH ALL DATA */
async function refreshDashboard() {
    await Promise.all([
        loadIncidents(),
        loadTeamMembers(),
        loadResources(),
        loadVictims(),
        loadHistory()
    ]);

    updateDashboard();
}

/* LOGOUT */
function logout() {
    if (confirm("Are you sure you want to logout?")) {
        window.location.href = "login.html";
    }
}

/* START APPLICATION */
document.addEventListener("DOMContentLoaded", () => {
    setupNavigation();
    setupIncidentForm();
    setupTeamForm();
    setupVictimForm();
    setupResourceForm();
    setupIncidentSearch();
    initializeMap();
    refreshDashboard();
});

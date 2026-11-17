# Emergency Response Dashboard

## Local XAMPP

1. Start Apache and MySQL in XAMPP.
2. Import `setup.sql` using phpMyAdmin.
3. Open `http://localhost/emergency-dashboard/login.html`.
4. Demo login: `admin` / `admin123`.

On this local URL, the dashboard uses its PHP/MySQL endpoints. The initial
demo password is upgraded to a password hash after its first successful login.

## Vercel deployment

Vercel runs the Express/MongoDB backend; it does not use the XAMPP PHP/MySQL
backend. The Express app is exported from `server.js`, and `npm run build`
copies the dashboard and login assets into Vercel's static `public/` folder.

Before deploying, configure these values in the Vercel project settings:

- `MONGODB_URI`: a valid MongoDB Atlas connection string
- `DB_NAME`: `emergency_dashboard`
- `ADMIN_USERNAME`: the deployment's login name
- `ADMIN_PASSWORD`: a long, unique password
- `SESSION_SECRET`: a separate random secret of at least 32 bytes

Never commit `.env` or paste these secrets into source code. The local `.env`
file is ignored by Git and Vercel. The current checked-in `.env.example` only
contains placeholders; the deployed database must be configured separately.

Deploy using a Vercel account and CLI (`npx vercel` for a preview, then
`npx vercel --prod` after checking the preview). Use `npx vercel dev` to check
Vercel routing locally. The static dashboard selects PHP endpoints on the
XAMPP URL and Express endpoints on hosted URLs.

## Data notes

The XAMPP data is in MySQL; the cloud API uses MongoDB. The two databases do
not sync automatically. A cloud deployment starts with empty MongoDB
collections until data is migrated or entered through the hosted dashboard.

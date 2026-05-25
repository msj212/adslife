# AdsLife — Setup Guide

## Prerequisites
- PHP 8.1+
- MySQL 8.0+
- Node.js 18+
- `php -S` (built-in server) or Apache/Nginx

---

## 1. Database Setup

```bash
mysql -u root -p < backend/schema.sql
```

This creates the `adslife` database, all tables, and seeds demo data.

**Demo credentials (password: `password123`):**
| Email | Role |
|-------|------|
| arjun@demo.com | User |
| vendor@demo.com | Vendor |
| admin@demo.com | Admin |

---

## 2. Backend (PHP)

```bash
cd backend
php -S localhost:8000
```

Or configure Apache/Nginx to serve `backend/` at port 8000.

**Optional: environment variables**
```bash
export DB_HOST=localhost
export DB_NAME=adslife
export DB_USER=root
export DB_PASS=yourpassword
export JWT_SECRET=change_me_in_production
```

---

## 3. Frontend (React)

```bash
cd frontend
npm install
npm run dev       # dev server at http://localhost:5173
# or
npm run build     # production build in dist/
```

The dev server proxies `/api/*` to `http://localhost:8000`.

---

## Project Structure

```
AdsLife/
├── backend/
│   ├── api/           All PHP API endpoints
│   ├── config/        DB config + app config
│   ├── middleware/     CORS, Auth (JWT)
│   └── services/      CoinsService, StreakService, BadgeService, FraudDetector
├── frontend/
│   ├── src/
│   │   ├── components/ Shared UI components
│   │   ├── pages/      All route pages
│   │   ├── store/      Zustand state stores
│   │   ├── hooks/      Custom React hooks
│   │   ├── types/      TypeScript interfaces
│   │   └── utils/      Axios API client + endpoints
│   └── public/sw.js   Service Worker for offline cache
└── schema.sql         Full MySQL schema + seed data
```

---

## Pages / Routes

| URL | Description | Role |
|-----|-------------|------|
| `/feed` | Personalized + Trending offers feed | All |
| `/leaderboard` | City leaderboard with podium | All |
| `/spin` | Daily spin-to-win wheel | All |
| `/profile` | User profile, badges, streak, coins | All |
| `/group-deals` | Active group deals | All |
| `/vendor/dashboard` | Vendor analytics overview | Vendor/Admin |
| `/vendor/heatmap` | Customer location heatmap (Leaflet) | Vendor/Admin |
| `/vendor/audience` | Device/time/city audience insights | Vendor/Admin |
| `/vendor/benchmark` | Radar chart vs category average | Vendor/Admin |
| `/vendor/roi/:offerId` | ROI calculator with funnel chart | Vendor/Admin |
| `/vendor/ab-test` | A/B test dashboard | Vendor/Admin |
| `/vendor/targeting` | Neighborhood targeting map | Vendor/Admin |
| `/vendor/budget` | Smart budget suggester wizard | Vendor/Admin |
| `/admin/fraud` | Fraud detection dashboard | Admin only |

---

## Free APIs Used

| API | Purpose | Key Required |
|-----|---------|-------------|
| Nominatim (OpenStreetMap) | Geocoding | No |
| Leaflet.js + OSM tiles | Maps, heatmap | No |
| MyMemory Translate | Multi-language (5000 req/day) | No |
| Web Share API | Native share sheet | No |
| Service Worker + Cache API | Offline caching | No |
| IndexedDB (idb library) | Offline data storage | No |
| Google Fonts | Inter + Plus Jakarta Sans | No |

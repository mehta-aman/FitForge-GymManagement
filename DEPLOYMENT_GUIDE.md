# FitForge 24/7 Zero-Cost Cloud Deployment Guide

This guide explains how to deploy the FitForge Gym Management System to the cloud for free with 24/7 uptime, automated SSL, and database backups.

---

## Architecture Overview

```mermaid
flowchart LR
    A["GitHub Repository (fitforge)"] --> B["Railway / Render (Web Container)"]
    C["Aiven MySQL / Railway DB (Free 5GB)"] --> B
    B --> D["Cloudflare CDN & Free Domain"]
    D --> E["Admins & Members on Phone/Desktop 24/7"]
```

---

## Option 1: Railway (Easiest — 1-Click DB + Web App)

1. **Sign Up:** Go to [railway.app](https://railway.app) and sign in with GitHub.
2. **Create MySQL Service:**
   - Click **New Project** > **Provision MySQL**.
   - Under the MySQL service **Variables** tab, you will see `MYSQL_URL`, `MYSQLHOST`, `MYSQLUSER`, `MYSQLPASSWORD`, `MYSQLDATABASE`.
3. **Import Database:**
   - Connect using any DB tool (TablePlus, DBeaver, or command line) using the Railway Public URL:
     ```bash
     # Single-command full v2.0 import (recommended for fresh databases):
     mysql -h <MYSQLHOST> -u <MYSQLUSER> -p<MYSQLPASSWORD> -P <MYSQLPORT> <MYSQLDATABASE> < gym/migrations/00_full_fitforge_v2_schema_and_seed.sql

     # OR run the incremental sequence if upgrading an existing pre-v1 database:
     mysql -h <MYSQLHOST> -u <MYSQLUSER> -p<MYSQLPASSWORD> -P <MYSQLPORT> <MYSQLDATABASE> < gym/migrations/01_priority1_schema_enhancements.sql
     mysql -h <MYSQLHOST> -u <MYSQLUSER> -p<MYSQLPASSWORD> -P <MYSQLPORT> <MYSQLDATABASE> < gym/migrations/02_priority2_schema_enhancements.sql
     mysql -h <MYSQLHOST> -u <MYSQLUSER> -p<MYSQLPASSWORD> -P <MYSQLPORT> <MYSQLDATABASE> < gym/migrations/03_member_profile_metrics.sql
     ```
4. **Deploy Web Container:**
   - Click **New Service** > **GitHub Repo** > Select this repository.
   - Railway will automatically detect the [Dockerfile](file:///Users/amanmehta/Downloads/GYM-Management-System-using-PHP/Dockerfile) and build it.
   - Under the Web Service **Variables** tab, add a Reference:
     - `DATABASE_URL` = `${{MySQL.MYSQL_URL}}`
5. **Generate Domain:**
   - Go to Web Service **Settings** > **Networking** > **Generate Domain**.
   - Your site is immediately live at `https://fitforge-production.up.railway.app`!

---

## Option 2: Aiven MySQL (5GB Free Always) + Render.com

1. **Database on Aiven:**
   - Sign up at [aiven.io](https://aiven.io).
   - Create a free MySQL service (5GB storage, automatic daily backups, SSL).
   - Copy the Service URI (`mysql://avnadmin:password@host:port/defaultdb?ssl-mode=REQUIRED`).
   - Import the schema and seed file into Aiven:
     ```bash
     mysql -h <AIVEN_HOST> -u <AIVEN_USER> -p<AIVEN_PASSWORD> -P <AIVEN_PORT> --ssl-mode=REQUIRED <AIVEN_DB> < gym/migrations/00_full_fitforge_v2_schema_and_seed.sql
     ```
2. **Web Container on Render:**
   - Sign up at [render.com](https://render.com).
   - Click **New** > **Web Service** > Connect GitHub Repository.
   - Environment: **Docker**.
   - Under **Environment Variables**, add:
     - `DATABASE_URL` = `your_aiven_service_uri`
   - Click **Deploy Web Service**.
   - Your site is live at `https://fitforge-xxxx.onrender.com`!

---

## Option 3: Local 24/7 Hosting via Cloudflare Named Tunnel (Zero Server Cost)

If you have a spare Mac Mini, Raspberry Pi, or home server running:
1. Create a permanent named tunnel on [dash.cloudflare.com](https://dash.cloudflare.com) (Zero Trust > Tunnels).
2. Install cloudflared as a system service:
   ```bash
   sudo cloudflared service install <TOKEN>
   ```
3. Route your custom domain (e.g., `gym.yourdomain.com`) directly to `http://localhost:8080`.
4. Cloudflare automatically provides global CDN, DDoS protection, and SSL encryption for free.

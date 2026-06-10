# potamagic - POTA Activation Tracker

A premium web application for Amateur Radio operators participating in the Parks on the Air (POTA) program. It allows activators to log portable activations, templates their station equipment, look up global park details automatically using the POTA.app API, and provides a rich analytics dashboard with search, filter, and grouping capabilities.

Designed for the standard Apache-MySQL-PHP (LAMP/WAMP) web server stack.

---

## Features

- **POTA.app API Lookup**: Instantly fetches park name, latitude, and longitude on typing the park reference (e.g. `US-0001`).
- **Operating Dashboard**: Real-time stats (total activations, QSOs, unique parks) and visual breakdown charts (Bands & Modes) powered by Chart.js.
- **Search, Filter & Grouping**: Search activations by keyword, filter by band or mode, and dynamically group results by Location Prefix, Month & Year, or Callsign.
- **Equipment Profiles**: Create templates of your typical portable/base configurations (transceiver, antenna, power) and autofill forms when logging activations.
- **Verification Simulator**: Log verification links to a local `mail_log.txt` file and show them on screen for easy local testing without configuring an SMTP server.
- **User Tiers**: 
  - *Unregistered Guests*: Search, filter, group, and view activation details.
  - *Registered Users*: Access to manage activations and equipment profiles.
  - *Administrators*: Access to User Management, Homepage Slider Management, and Metadata Editors (add/remove bands and modes).

---

## Installation & Setup

### 1. Database Setup
Import the `schema.sql` file into your MySQL database server (e.g., via phpMyAdmin, CLI, or Workbench). This will:
- Create the database `pota_tracker`.
- Build the required tables.
- Seed default operating bands, modes, and the default administrator.

### 2. Configure Settings
- Database credentials: Edit [config/db.php](config/db.php).
- Email verification / SMTP settings: Edit [config/smtp.php](config/smtp.php).
  - Leave `SIMULATE_EMAIL` as `true` to test account verification locally without SMTP (verification links will print in `mail_log.txt` and display on-screen).

### 3. Add PHPMailer (For Real Email Delivery)
If you set `SIMULATE_EMAIL` to `false` in `config/smtp.php`, you will need to add PHPMailer:
1. Download PHPMailer from [github.com/PHPMailer/PHPMailer](https://github.com/PHPMailer/PHPMailer).
2. Place the following three core files in the `lib/PHPMailer/` directory:
   - `Exception.php`
   - `PHPMailer.php`
   - `SMTP.php`

### 4. Running Locally
Run the built-in PHP development server from your terminal inside the project root:
```bash
php -S localhost:8000
```
Open your browser and navigate to `http://localhost:8000`.

### 5. Pre-seeded Administrator Login
- **Username / Callsign**: `admin`
- **Password**: `admin`

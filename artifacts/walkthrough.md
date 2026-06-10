# POTA Activation Tracker - Walkthrough

The Parks on the Air (POTA) Activation Tracker application has been successfully built! Below is a detailed walkthrough of the system architecture, features, and instructions on how to set up and verify the application locally.

---

## 📸 Generated Hero Slider Assets

To ensure a premium first impression, we have generated three high-quality, themed outdoor ham radio landscapes for the home page slider. They are saved in `uploads/slider/`:

![Slider Image 1 - Mountain Activation](file:///C:/Users/Marek/.gemini/antigravity-ide/brain/bd3ccc21-e8a4-4085-8bb7-5046be72b80c/pota_hero_mountain_activation_1781114713064.png)

![Slider Image 2 - Table Rig](file:///C:/Users/Marek/.gemini/antigravity-ide/brain/bd3ccc21-e8a4-4085-8bb7-5046be72b80c/pota_hero_park_rig_1781114727286.png)

![Slider Image 3 - Lake Setup](file:///C:/Users/Marek/.gemini/antigravity-ide/brain/bd3ccc21-e8a4-4085-8bb7-5046be72b80c/pota_hero_lake_setup_1781114742552.png)

---

## 🛠️ Step 1: Initialize the MySQL Database

1. Open your MySQL client (e.g., phpMyAdmin, MySQL Workbench, or CLI).
2. Import the [schema.sql](file:///C:/Users/Marek/.gemini/antigravity-ide/scratch/pota_tracker/schema.sql) file. This script will:
   - Create the `pota_tracker` database.
   - Build tables for `users`, `bands`, `modes`, `equipment_profiles`, `activations`, `activation_images`, and `slider_images`.
   - Seed default operating bands (20m, 40m, etc.) and modes (SSB, CW, FT8).
   - Seed a default Administrator account (`admin` / `admin`).

---

## 🚀 Step 2: Run a Local PHP Server

To test the application locally, run PHP's built-in web server.

```powershell
# Open terminal inside the project directory:
cd C:\Users\Marek\.gemini\antigravity-ide\scratch\pota_tracker

# Start PHP built-in web server:
php -S localhost:8000
```

Once running, navigate to `http://localhost:8000` in your web browser.

---

## 🧭 Step 3: User Journey Walkthrough

### 1. Unregistered User (Visitor) Flow
- Open the homepage (`index.php`). You will see the picture slider rotating through the generated ham radio outdoor images.
- Explore the **Performance Dashboard**. If no activations are logged yet, a clean placeholder message will guide you.
- Below the dashboard, you will find the search and filters. You can search by keywords and filter by band/mode.
- Adjust the **Group Results By** dropdown to dynamically group the activations list by **Location Prefix** (e.g. US, PL), **Month & Year**, or **Activator Callsign**.
- Click the **Details** button on any card to see coordinates, parking spot details, parking conditions, cell coverage strength, directions, and the uploaded photos.

### 2. Registered User (Activator) Flow
- Click **Register** in the top navigation. Create a new callsign account.
- **Verification Sim**: After registering, a banner will appear at the top. Since `SIMULATE_EMAIL` is enabled in `config/smtp.php`, it will log the activation email details to `mail_log.txt` and display a local bypass link. Click the bypass link to verify the account immediately.
- Go to **Gear Profiles** (`equipment_profiles.php`). Create a profile (e.g. "SOTA Rig", Radio: Icom IC-705, Antenna: EFHW, Power: 10W).
- Go to **Add Activation** (`add_activation.php`).
  - Choose your gear profile from the dropdown; the station equipment fields will populate automatically.
  - In the "Park ID" field, type `US-0001` or `PL-0001`. The system will fetch details from the `pota.app` API via AJAX, display a success status, and auto-populate the park name, latitude, and longitude.
  - Select your operating bands and modes from the checkboxes, input the total QSO count, add parking coordinates and parking conditions (e.g., paved lot, free, roadside), cellular strength, and directions.
  - Upload up to 5 photos and save!

### 3. Administrator Flow
- Log in with the pre-seeded admin credentials (`admin` / `admin`).
- Go to **Users** (`admin_users.php`): You can search users, promote/demote accounts (Admin / Registered), manually verify/unverify emails, or delete accounts.
- Go to **Slider** (`admin_slider.php`): You can manage the homepage slideshow by uploading new images or deleting existing slides.
- Go to **Metadata** (`admin_metadata.php`): You can add new operating bands (e.g., "17m") or modes (e.g., "RTTY") or delete unused ones. The activation form checkboxes will update dynamically!

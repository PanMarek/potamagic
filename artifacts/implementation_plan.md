# POTA Activation Tracker - Implementation Plan

We will build a web application for ham radio operators participating in the Parks on the Air (POTA) program. The application collects, manages, and displays activation records, using the public `api.pota.app` API for park validation. The project is designed to run in a standard Apache-MySQL-PHP (LAMP/WAMP) environment.

---

## Workspace Subdirectory

We will create the project in a subdirectory:
[pota_tracker](file:///C:/Users/Marek/.gemini/antigravity-ide/scratch/pota_tracker)

> [!TIP]
> Once the plan is approved, we recommend setting `C:\Users\Marek\.gemini\antigravity-ide\scratch\pota_tracker` as the active workspace in your IDE.

---

## User-Specified Design Decisions & Adjustments

Based on your latest feedback, we have made the following improvements:

1. **Admin-Defined Bands & Modes**:
   - Instead of hardcoding bands and modes, they will be stored in database tables (`bands` and `modes`).
   - Administrators will have an interface in the admin panel to add and delete bands and modes.
   - The activation form will query these tables to render the checkboxes dynamically.
2. **Parking Conditions**:
   - Added a **Parking Conditions** field (e.g., paved lot, gravel, roadside, paid parking) under localization details.
3. **Flexible Email Verification**:
   - We will support both PHPMailer SMTP and a **Local Simulation Mode**.
   - A configuration setting (`SIMULATE_EMAIL`) in `config/smtp.php` can be set to `true`. If true, the system will save the activation email to a local file (`mail_log.txt`) and display the activation link directly on the screen for easy testing. If false, it will send a real email using PHPMailer.

---

## Database Schema (MySQL)

We will create the `schema.sql` file containing:

1. **`users`**:
   - `id` (INT AUTO_INCREMENT PRIMARY KEY)
   - `username` (VARCHAR(50) UNIQUE, NOT NULL)
   - `email` (VARCHAR(100) UNIQUE, NOT NULL)
   - `password_hash` (VARCHAR(255) NOT NULL)
   - `role` (ENUM('registered', 'admin') DEFAULT 'registered')
   - `is_verified` (TINYINT(1) DEFAULT 0)
   - `verification_token` (VARCHAR(100) NULL)
   - `created_at` (TIMESTAMP DEFAULT CURRENT_TIMESTAMP)

2. **`bands`**:
   - `id` (INT AUTO_INCREMENT PRIMARY KEY)
   - `name` (VARCHAR(20) UNIQUE, NOT NULL) -- e.g., '20m', '40m'

3. **`modes`**:
   - `id` (INT AUTO_INCREMENT PRIMARY KEY)
   - `name` (VARCHAR(20) UNIQUE, NOT NULL) -- e.g., 'SSB', 'CW', 'FT8'

4. **`equipment_profiles`**:
   - `id` (INT AUTO_INCREMENT PRIMARY KEY)
   - `user_id` (INT, FOREIGN KEY referencing `users.id` ON DELETE CASCADE)
   - `profile_name` (VARCHAR(100) NOT NULL)
   - `transceiver` (VARCHAR(100))
   - `antenna` (VARCHAR(100))
   - `power_source` (VARCHAR(100))
   - `power_watts` (INT)
   - `additional_equipment` (TEXT)
   - `created_at` (TIMESTAMP DEFAULT CURRENT_TIMESTAMP)

5. **`activations`**:
   - `id` (INT AUTO_INCREMENT PRIMARY KEY)
   - `user_id` (INT, FOREIGN KEY referencing `users.id` ON DELETE CASCADE)
   - `activation_date` (DATE NOT NULL)
   - `park_reference` (VARCHAR(20) NOT NULL) -- e.g., US-0001
   - `park_name` (VARCHAR(150) NOT NULL)
   - `qso_count` (INT DEFAULT 0)
   - `bands` (VARCHAR(255)) -- Comma-separated list of checked bands (e.g. '20m, 40m')
   - `modes` (VARCHAR(255)) -- Comma-separated list of checked modes (e.g. 'SSB, CW')
   - `transceiver` (VARCHAR(100))
   - `antenna` (VARCHAR(100))
   - `power_source` (VARCHAR(100))
   - `power_watts` (INT)
   - `additional_equipment` (TEXT)
   - `latitude` (DECIMAL(9, 6))
   - `longitude` (DECIMAL(9, 6))
   - `parking_coords` (VARCHAR(50))
   - `parking_conditions` (VARCHAR(100)) -- e.g., 'paved parking lot', 'roadside pull-off'
   - `cell_coverage` (VARCHAR(100))
   - `localization_notes` (TEXT)
   - `created_at` (TIMESTAMP DEFAULT CURRENT_TIMESTAMP)

6. **`activation_images`**:
   - `id` (INT AUTO_INCREMENT PRIMARY KEY)
   - `activation_id` (INT, FOREIGN KEY referencing `activations.id` ON DELETE CASCADE)
   - `image_path` (VARCHAR(255) NOT NULL)
   - `created_at` (TIMESTAMP DEFAULT CURRENT_TIMESTAMP)

7. **`slider_images`**:
   - `id` (INT AUTO_INCREMENT PRIMARY KEY)
   - `image_path` (VARCHAR(255) NOT NULL)
   - `uploaded_at` (TIMESTAMP DEFAULT CURRENT_TIMESTAMP)

---

## Directory Structure

```
pota_tracker/
│
├── config/
│   ├── db.php             # Database credentials and PDO connection
│   └── smtp.php           # SMTP credentials + SIMULATE_EMAIL toggle
│
├── css/
│   └── style.css          # Deep navy/amber theme with responsive layouts
│
├── js/
│   └── app.js             # Slider logic, Chart.js, and POTA API fetch
│
├── includes/
│   ├── header.php         # Navbar, authentication checks
│   └── footer.php         # Footer
│
├── lib/
│   └── PHPMailer/         # Core PHPMailer files (manually downloaded/created)
│
├── uploads/
│   ├── activations/       # Photo uploads from activators
│   └── slider/            # Admin uploaded hero images
│
├── index.php              # Slider + Dashboard + Grouped Activation List
├── activation_details.php # Complete view of single activation
├── add_activation.php     # Add activation (fetches POTA API, applies gear profiles)
├── edit_activation.php    # Edit activation
├── delete_activation.php  # Handles activation deletion
├── equipment_profiles.php # Manage pre-defined equipment templates
├── register.php           # Account registration
├── verify.php             # Email verification handler
├── login.php              # Login
├── logout.php             # Logout
├── admin_users.php        # Manage users, verify, change roles
├── admin_slider.php       # Upload/delete hero slider images
├── admin_metadata.php     # Admin panel to manage bands and modes
└── schema.sql             # SQL schema + seed default admin + default bands/modes
```

---

## Verification Plan

### Manual Verification Steps
1. **Database Setup**: Import `schema.sql`. Check that default bands (20m, 40m, etc.) and modes (SSB, CW, FT8) are seeded.
2. **Metadata Administration**: Log in as admin, navigate to `admin_metadata.php`, and verify you can add a new band (e.g., "17m") or delete one.
3. **Hero Slider Setup**: Log in as `admin`, upload a few hero pictures in `admin_slider.php`, and verify they display in the homepage slider.
4. **Registration Flow (Simulation Mode)**:
   - Register a new account with `SIMULATE_EMAIL = true`.
   - Verify that the success page displays a banner containing the activation link, and that `mail_log.txt` records the link.
   - Click the link to verify the account.
5. **Equipment Profile**: Create a profile in `equipment_profiles.php`.
6. **Activation Entry**:
   - Go to "Add Activation".
   - Select the equipment profile to populate gear.
   - Type a park ID (e.g., `US-0001`). Confirm that the park name, latitude, and longitude are fetched from `api.pota.app` and fill the form.
   - Fill in parking conditions, coordinates, cell coverage, upload photos, and save.
7. **Dashboard & Grouping**:
   - Check the Chart.js visual statistics.
   - Group the activation list by Location, Month, or Activator, and verify that cards group correctly.
8. **User Administration**:
   - Search for the test user and change their role or toggle verification status.

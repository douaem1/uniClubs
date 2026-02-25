# 🎓 UniClubs

**UniClubs** is a comprehensive university club and event management platform designed to streamline the organization, registration, and management of campus activities. It provides tailored interfaces for participants, organizers, and coordinators.

---

## 🚀 Key Features

### Participant
- **Browse Events:** View upcoming university events and club activities.
- **Registration:** Easily sign up for events and track registrations.
- **Profile Management:** Manage personal information and view participation history.

### Organizer
- **Dashboard:** Real-time statistics on event participation and capacity.
- **Event Creation:** Tools to create, manage, and publish events.
- **Communication:** Send emails to event participants directly from the platform.
- **Certification:** Generate and manage attestations for attendees using FPDF.

### Coordinator
- **Validation Flow:** Review and validate event requests from organizers.
- **System Oversight:** Monitor all platform activities and ensure university standards are met.
- **User Management:** Oversee roles and account permissions.

---

## 🛠️ Technology Stack

- **Backend:** PHP (Native)
- **Frontend:** HTML5, CSS3 (Vanilla), JavaScript, Bootstrap Icons
- **Database:** MySQL
- **Libraries:**
    - `PHPMailer`: For reliable email notifications.
    - `FPDF`: For PDF generation (attestations/certificates).

---

## 📁 Project Structure

```text
uniClubs/
├── coordinateur/     # Coordinator-specific features & dashboard
├── organisateur/     # Organizer tools, email sending, PDF generation
├── participant/      # Participant event browsing & profile
├── vendor/           # Composer dependencies (PHPMailer)
├── ConnDB.php        # Database connection configuration
└── composer.json     # Project dependencies
```

---

## ⚙️ Getting Started

### Prerequisites
- **XAMPP / WAMP / MAMP**
- **PHP 7.4+**
- **MySQL**

### Installation

1. **Clone the repository:**
   ```bash
   git clone <repository-url>
   ```

2. **Database Setup:**
   - Create a database named `uniclubs` in phpMyAdmin.
   - Import the provided SQL file (if available).

3. **Configuration:**
   - Open `ConnDB.php`.
   - Update your database credentials:
     ```php
     $db_host = 'localhost';
     $db_user = 'root';
     $db_pass = '';
     $db_name = 'uniclubs';
     ```

4. **Run the Application:**
   - Place the project in your `htdocs` or `www` folder.
   - Access it via `http://localhost/uniClubs/uniClubs/login.php`.

---

## 📄 License
This project is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

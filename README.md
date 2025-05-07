# 🎓 PeerTutor

**PeerTutor** is a full-stack web application designed to connect students with verified tutors for seamless academic assistance. The platform facilitates tutor discovery, session bookings, secure payments, and interactive communication, all within a user-friendly interface.

---

## 🚀 Features

- ✅ User authentication and registration (students and tutors)
- 🔍 Tutor search and filtering by subject and availability
- 📅 Booking and scheduling tutoring sessions
- 💳 Secure payments using Stripe API
- ⭐ Review and rating system
- 🛠️ Admin dashboard to manage users and reviews

---

## 🛠️ Tech Stack

- **Frontend:** HTML5, CSS3, JavaScript
- **Backend:** PHP
- **Database:** MySQL
- **Payment Gateway:** Stripe API
- **Video API:** Zoom API
- **Other Tools:** Git, GitHub, Dotenv

---

## 📂 Project Structure

```plaintext
PeerTutor/
├── about.php
├── admin_dashboard.php
├── admin_reports.php
├── admin_reviews.php
├── admin_tutors.php
├── book_session.php
├── chat.php
├── contact.php
├── db_connect.php
├── find_tutors.php
├── forgot_password.php
├── index.php
├── login.php
├── login_process.php
├── logout.php
├── my_sessions.php
├── privacy.php
├── rate_tutor.php
├── register.php
├── register_process.php
├── report_review.php
├── report_tutor.php
├── resend_verification.php
├── reset_password.php
├── stripe_webhook.php
├── uploads/
├── images/
├── styles/
├── .env
├── .gitignore
├── composer.json
├── composer.lock
├── package.json
├── package-lock.json
```

---

## ⚙️ Installation & Setup (with XAMPP)

### 1. Prerequisites

- Install [XAMPP](https://www.apachefriends.org/index.html)
- Install [Composer](https://getcomposer.org/)
- Install [Node.js](https://nodejs.org/)

### 2. Setup Steps

```bash
# Clone the repository
git clone https://github.com/Kumardeepsingh/PeerTutor.git
cd PeerTutor

# Install PHP dependencies
composer install

# Install Node.js dependencies
npm install
```

### 3. Database Setup

- Start Apache and MySQL in XAMPP
- Go to `http://localhost/phpmyadmin`
- Create a new database (e.g., `peertutor`)
- Import the `peertutor.sql` file
- Update your `.env` file or `db_connect.php` with:

```env
DB_HOST=localhost
DB_NAME=peertutor
DB_USER=root
DB_PASS=
```

---

## 🔐 Environment Variables

Create a `.env` file in the root directory with:

```env
# Stripe API Keys
STRIPE_SECRET_KEY=sk_test_your_secret_key
STRIPE_PUBLISHABLE_KEY=pk_test_your_publishable_key

# Zoom API Keys
ZOOM_API_KEY=your_zoom_api_key
ZOOM_API_SECRET=your_zoom_api_secret

# Database (if applicable)
DB_HOST=localhost
DB_NAME=peertutor
DB_USER=root
DB_PASS=
```

Load it using:

```php
require_once __DIR__ . '/vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();
```

---

## 🔌 API Integrations

### Stripe Integration

- Install Stripe PHP SDK:

```bash
composer require stripe/stripe-php
```

- Handles payments, commissions, and session confirmations.

### Zoom Integration

- Create JWT/OAuth app on [Zoom Marketplace](https://marketplace.zoom.us/)
- Use `/users/me/meetings` API to auto-schedule tutoring sessions

---

## ▶️ Run the Application

- Place the project in `C:/xampp/htdocs/PeerTutor`
- Visit `http://localhost/PeerTutor` in your browser

---

## 📬 Contact

- 📧 Email: [Kumardeepsingh@student.kpu.ca](mailto:Kumardeepsingh@student.kpu.ca)
- 💻 GitHub: [Kumardeepsingh](https://github.com/Kumardeepsingh)

---

## 🤝 Contributing

Contributions and suggestions are welcome! Please fork this repo and submit a pull request.

---

## 📄 License

This project is licensed under the [MIT License](LICENSE).

---

> “Empowering students through accessible and quality tutoring services.”

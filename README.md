# 📸 Instagram Clone

A full-featured Instagram-like social media web application built with PHP, MySQL, and JavaScript.

---

## 🚀 Features

- 👤 User Authentication (Sign up, Sign in, Forgot Password)
- 🏠 Home Feed with Posts
- ❤️ Like, Comment, Save Posts
- 📷 Create & Delete Posts
- 🎬 Stories & Reels
- 🔍 Explore Page
- 💬 Real-time Messaging (Socket.io)
- 🔔 Notifications
- 👥 Follow / Unfollow System
- 🚫 Block Users
- 🔒 Private Account Support
- 👑 Admin Panel
- 📝 Integrated Blog (WordPress)

---

## 🛠️ Tech Stack

| Layer | Technology |
|-------|-----------|
| Backend | PHP (MVC Architecture) |
| Database | MySQL |
| Frontend | HTML, CSS, JavaScript |
| Real-time | Node.js + Socket.io |
| Mailing | PHPMailer |

---

## ⚙️ Installation & Setup

### Requirements
- PHP >= 7.4
- MySQL
- Node.js
- Composer

### Steps

**1. Clone the repository**
```bash
git clone https://github.com/Gunjan29s/Instagram_clone.git
cd Instagram_clone
```

**2. Install PHP dependencies**
```bash
composer install
```

**3. Setup Database**
- Create a MySQL database
- Import the schema:
```bash
mysql -u root -p your_database < config/schema.sql
```

**4. Configure the project**

Edit `config/database.php` and add your database credentials:
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'your_database_name');
define('DB_USER', 'your_username');
define('DB_PASS', 'your_password');
```

Edit `config/mailing.php` and add your SMTP credentials for email functionality.

**5. Start the Socket server**
```bash
node socket-server.js
```

**6. Run the project**

Start your local server (XAMPP/WAMP) and open:
```
http://localhost/Instagram_clone/
```

---

## 📁 Project Structure

```
Instagram_clone/
├── admin/              # Admin panel
├── components/         # Reusable UI components
├── config/             # Database, mail, security config
├── controllers/        # Application controllers
├── models/             # Database models
├── views/              # Page views
├── css/                # Stylesheets
├── js/                 # JavaScript files
├── uploads/            # User uploaded media
├── composer.json       # PHP dependencies
├── socket-server.js    # Real-time socket server
├── router.php          # URL routing
└── index.php           # Entry point
```

---

## 👤 Admin Panel

Access the admin panel at:
```
http://localhost/Instagram_clone/admin/

---

## 🙋‍♂️ Author

**Gunjan** — [GitHub](https://github.com/Gunjan29s)

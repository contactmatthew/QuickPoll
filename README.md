# 🗳️ QuickPoll - Instant Polling & Voting Platform

<div align="center">

![QuickPoll Logo](https://img.shields.io/badge/QuickPoll-Polling%20Platform-6366f1?style=for-the-badge&logo=chart-bar&logoColor=white)
![PHP](https://img.shields.io/badge/PHP-7.4+-777BB4?style=for-the-badge&logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-5.7+-4479A1?style=for-the-badge&logo=mysql&logoColor=white)
![License](https://img.shields.io/badge/License-Open%20Source-green?style=for-the-badge)

**A simple, secure, and responsive polling/voting website that allows users to create polls with images and share them instantly - no login required!**

[🚀 Features](#-features) • [📦 Installation](#-installation) • [💻 Usage](#-usage) • [🔒 Security](#-security-features) • [📁 Structure](#-file-structure)

</div>

---

## ✨ Features

<div align="center">

| 🎯 Feature | 📝 Description |
|:---:|:---|
| 🚫 **No Login** | Create polls instantly without any registration |
| 🖼️ **Image Support** | Add images to poll options for visual appeal |
| 🔐 **IP-Based Voting** | One vote per IP address to prevent cheating |
| 🔗 **Shareable Links** | Easy sharing with unique poll links |
| ⏰ **Expiration System** | Set expiration in hours (1-24) or days (1-30) |
| 🧹 **Auto Cleanup** | Expired polls are automatically removed |
| 🏆 **Winner Display** | Shows winner even after expiration |
| 🌙 **Dark Mode** | Beautiful dark theme UI |
| 📱 **Responsive Design** | Works perfectly on all devices |
| 🛡️ **Security Features** | Rate limiting, input validation, DDoS protection |
| 🔑 **Password Protection** | Optional password protection for polls |

</div>

---

## 🎨 Screenshots

> **Note:** Add screenshots of your application here to showcase the beautiful UI!

---

## 📋 Requirements

<div align="center">

| Component | Version |
|:---:|:---:|
| **PHP** | 7.4 or higher |
| **MySQL** | 5.7 or higher |
| **Apache** | With mod_rewrite enabled |
| **XAMPP** | For localhost development |

</div>

---

## 🚀 Installation

### Step 1: 📊 Database Setup

1. Open **phpMyAdmin** → `http://localhost/phpmyadmin`
2. Import the `database.sql` file to create the database and tables
3. Or run the SQL commands manually in MySQL

### Step 2: ⚙️ Configuration

1. Edit `config.php` if needed (default settings work for XAMPP):
   ```php
   define('DB_HOST', 'localhost');
   define('DB_USER', 'root');  // Change for production
   define('DB_PASS', '');      // Change for production
   define('DB_NAME', 'quickpoll');
   ```
   
   ⚠️ **Security Note:** Always change default credentials in production!

### Step 3: 📁 File Permissions

1. Create the `uploads` directory:
   ```bash
   mkdir uploads
   chmod 755 uploads
   ```

2. Make sure PHP has write permissions to the `uploads` directory

### Step 4: 🌐 Access the Website

1. Place all files in your XAMPP `htdocs/QuickPoll` directory
2. Access via: `http://localhost/QuickPoll/`

---

## 💻 Usage

### 📝 Creating a Poll

1. Go to the homepage
2. Enter a poll title
3. Set expiration (hours or days)
4. Add at least 2 options
5. Optionally add images to options
6. Click **"Create Poll"**
7. Share the generated link

### 🗳️ Voting

1. Open the poll link
2. Click on an option to vote
3. View real-time results (if you've voted or poll is expired)

---

## 🔒 Security Features

### 🚦 Rate Limiting

- ⏱️ Maximum **5 polls** per IP per hour
- ⏱️ Maximum **10 votes** per IP per minute
- 🚫 Temporary IP blocking for excessive requests

### ✅ Input Validation

- 🛡️ SQL injection prevention (prepared statements)
- 🛡️ XSS protection (input sanitization)
- 📎 File upload validation (type and size checks)
- 🖼️ Image verification

### 🛡️ DDoS Protection

- 🚦 Rate limiting per IP address
- ⏱️ Request throttling
- 🚫 Automatic blocking of suspicious IPs

---

## 📁 File Structure

```
QuickPoll/
├── 📂 api/
│   ├── 📄 create_poll.php    # Create new poll
│   ├── 📄 vote.php           # Submit vote
│   ├── 📄 get_poll.php       # Get poll data
│   └── 📄 get_all_polls.php  # Get all active polls
├── 📂 cron/
│   └── 📄 cleanup_expired.php # Cleanup expired polls
├── 📂 css/
│   └── 📄 style.css          # Dark mode styles
├── 📂 js/
│   ├── 📄 main.js            # Homepage functionality
│   └── 📄 poll.js            # Poll view functionality
├── 📂 uploads/               # Image uploads directory
├── 📄 config.php             # Configuration
├── 📄 security.php           # Security functions
├── 📄 database.sql           # Database schema
├── 📄 index.html             # Homepage
├── 📄 poll.html              # Poll view page
├── 📄 .htaccess              # Apache security config
└── 📄 README.md              # This file
```

---

## ⏰ Cron Job Setup (Optional)

To automatically clean up expired polls, set up a cron job:

### Linux/Mac:
```bash
# Run every hour
0 * * * * /usr/bin/php /path/to/QuickPoll/cron/cleanup_expired.php
```

### Windows Task Scheduler:
- Create a task that runs `php cleanup_expired.php` every hour
- Point to: `C:\xampp\htdocs\QuickPoll\cron\cleanup_expired.php`

---

## 🗄️ Database Maintenance

Expired polls are automatically marked and cleaned up:
- ✅ Expired polls are marked immediately when accessed
- 🗑️ Polls older than 7 days after expiration are deleted
- 🏆 Winner information is preserved before deletion

---

## 🔧 Troubleshooting

<details>
<summary><b>🖼️ Images not displaying</b></summary>

- Check that `uploads` directory exists and has write permissions
- Verify image paths in database
- Check browser console for errors
</details>

<details>
<summary><b>❌ Can't create polls</b></summary>

- Check database connection in `config.php`
- Verify database exists and tables are created
- Check PHP error logs
</details>

<details>
<summary><b>🗳️ Voting not working</b></summary>

- Verify IP address detection
- Check if you've already voted (one vote per IP)
- Ensure poll hasn't expired
</details>

---

## 📊 Tech Stack

<div align="center">

![PHP](https://img.shields.io/badge/PHP-777BB4?style=flat-square&logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-4479A1?style=flat-square&logo=mysql&logoColor=white)
![JavaScript](https://img.shields.io/badge/JavaScript-F7DF1E?style=flat-square&logo=javascript&logoColor=black)
![HTML5](https://img.shields.io/badge/HTML5-E34F26?style=flat-square&logo=html5&logoColor=white)
![CSS3](https://img.shields.io/badge/CSS3-1572B6?style=flat-square&logo=css3&logoColor=white)
![Bootstrap](https://img.shields.io/badge/Bootstrap-7952B3?style=flat-square&logo=bootstrap&logoColor=white)

</div>

---

## 📄 License

This project is **open source** and available for personal and commercial use.

---

## 💬 Support

For issues or questions, please check:
- 🔧 Database connection settings
- 📁 File permissions
- 📝 PHP error logs
- 🌐 Apache error logs

---

## 👨‍💻 Author

<div align="center">

**Made with ❤️ by [James Matthew Dela Torre](https://github.com/contactmatthew)**

[![GitHub](https://img.shields.io/badge/GitHub-181717?style=for-the-badge&logo=github&logoColor=white)](https://github.com/contactmatthew)
[![Facebook](https://img.shields.io/badge/Facebook-1877F2?style=for-the-badge&logo=facebook&logoColor=white)](https://www.facebook.com/mtthw28)
[![Buy Me a Coffee](https://img.shields.io/badge/Buy_Me_A_Coffee-FFDD00?style=for-the-badge&logo=buy-me-a-coffee&logoColor=black)](https://buymeacoffee.com/isshiki)

</div>

---

<div align="center">

### ⭐ Star this repo if you find it helpful!

**QuickPoll** - Creating polls has never been easier! 🚀

</div>

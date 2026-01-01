# QuickPoll - Instant Polling & Voting Platform

QuickPoll is a simple, secure, and responsive polling/voting website that allows users to create polls with images and share them instantly - no login required!

## Features

- ✅ **No Login Required** - Create polls instantly
- ✅ **Image Support** - Add images to poll options
- ✅ **IP-Based Voting** - One vote per IP address to prevent cheating
- ✅ **Shareable Links** - Easy sharing with unique poll links
- ✅ **Expiration System** - Set expiration in hours (1-24) or days (1-30)
- ✅ **Auto Cleanup** - Expired polls are automatically removed
- ✅ **Winner Display** - Shows winner even after expiration
- ✅ **Dark Mode** - Beautiful dark theme
- ✅ **Responsive Design** - Works on all devices
- ✅ **Security Features** - Rate limiting, input validation, DDoS protection

## Requirements

- PHP 7.4 or higher
- MySQL 5.7 or higher
- Apache web server with mod_rewrite
- XAMPP (for localhost development)

## Installation

### 1. Database Setup

1. Open phpMyAdmin (http://localhost/phpmyadmin)
2. Import the `database.sql` file to create the database and tables
3. Or run the SQL commands manually in MySQL

### 2. Configuration

1. Edit `config.php` if needed (default settings work for XAMPP):
   ```php
   define('DB_HOST', 'localhost');
   define('DB_USER', 'root');  // Change for production
   define('DB_PASS', '');      // Change for production
   define('DB_NAME', 'quickpoll');
   ```
   
   **Security Note:** Always change default credentials in production!

### 3. File Permissions

1. Create the `uploads` directory:
   ```bash
   mkdir uploads
   chmod 755 uploads
   ```

2. Make sure PHP has write permissions to the `uploads` directory

### 4. Access the Website

1. Place all files in your XAMPP `htdocs/QuickPoll` directory
2. Access via: `http://localhost/QuickPoll/`

## Usage

### Creating a Poll

1. Go to the homepage
2. Enter a poll title
3. Set expiration (hours or days)
4. Add at least 2 options
5. Optionally add images to options
6. Click "Create Poll"
7. Share the generated link

### Voting

1. Open the poll link
2. Click on an option to vote
3. View real-time results (if you've voted or poll is expired)

## Security Features

### Rate Limiting
- Maximum 5 polls per IP per hour
- Maximum 10 votes per IP per minute
- Temporary IP blocking for excessive requests

### Input Validation
- SQL injection prevention (prepared statements)
- XSS protection (input sanitization)
- File upload validation (type and size checks)
- Image verification

### DDoS Protection
- Rate limiting per IP address
- Request throttling
- Automatic blocking of suspicious IPs

## File Structure

```
QuickPoll/
├── api/
│   ├── create_poll.php    # Create new poll
│   ├── vote.php           # Submit vote
│   └── get_poll.php       # Get poll data
├── cron/
│   └── cleanup_expired.php # Cleanup expired polls
├── css/
│   └── style.css          # Dark mode styles
├── js/
│   ├── main.js            # Homepage functionality
│   └── poll.js            # Poll view functionality
├── uploads/               # Image uploads directory
├── config.php             # Configuration
├── security.php           # Security functions
├── database.sql           # Database schema
├── index.html             # Homepage
├── poll.html              # Poll view page
├── .htaccess              # Apache security config
└── README.md              # This file
```

## Cron Job Setup (Optional)

To automatically clean up expired polls, set up a cron job:

```bash
# Run every hour
0 * * * * /usr/bin/php /path/to/QuickPoll/cron/cleanup_expired.php
```

Or for Windows Task Scheduler:
- Create a task that runs `php cleanup_expired.php` every hour
- Point to: `C:\xampp\htdocs\QuickPoll\cron\cleanup_expired.php`

## Database Maintenance

Expired polls are automatically marked and cleaned up:
- Expired polls are marked immediately when accessed
- Polls older than 7 days after expiration are deleted
- Winner information is preserved before deletion

## Troubleshooting

### Images not displaying
- Check that `uploads` directory exists and has write permissions
- Verify image paths in database
- Check browser console for errors

### Can't create polls
- Check database connection in `config.php`
- Verify database exists and tables are created
- Check PHP error logs

### Voting not working
- Verify IP address detection
- Check if you've already voted (one vote per IP)
- Ensure poll hasn't expired

## License

This project is open source and available for personal and commercial use.

## Support

For issues or questions, please check:
- Database connection settings
- File permissions
- PHP error logs
- Apache error logs


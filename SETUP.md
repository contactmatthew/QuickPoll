# QuickPoll Setup Guide

## Quick Start (XAMPP Localhost)

### Step 1: Database Setup

1. Start XAMPP and ensure MySQL is running
2. Open phpMyAdmin: http://localhost/phpmyadmin
3. Click on "Import" tab
4. Choose file: `database.sql`
5. Click "Go" to import
6. Database `quickpoll` will be created with all necessary tables

### Step 2: Verify Configuration

1. Open `config.php`
2. Verify database settings (default XAMPP settings):
   ```php
   DB_HOST: localhost
   DB_USER: root        // Change in production!
   DB_PASS: (empty)     // Change in production!
   DB_NAME: quickpoll
   ```
   
   **Important:** Change default credentials before deploying to production!

### Step 3: Verify Directory Structure

Ensure these directories exist:
- `uploads/` - For storing poll images
- `api/` - API endpoints
- `css/` - Stylesheets
- `js/` - JavaScript files
- `cron/` - Cleanup scripts

### Step 4: Test the Application

1. Open browser: http://localhost/QuickPoll/
2. Create a test poll:
   - Enter a title
   - Add 2-3 options
   - Optionally add images
   - Set expiration
   - Click "Create Poll"
3. Share the link and test voting

## Troubleshooting

### Database Connection Error
- Check MySQL is running in XAMPP
- Verify database name is `quickpoll`
- Check `config.php` credentials

### Images Not Uploading
- Check `uploads/` directory exists
- Verify write permissions (chmod 755 on Linux/Mac)
- Check PHP `upload_max_filesize` in php.ini

### Can't Vote
- Check if poll has expired
- Verify you haven't already voted (one vote per IP)
- Check browser console for errors

### 404 Errors
- Ensure `.htaccess` is in root directory
- Check Apache `mod_rewrite` is enabled
- Verify file paths are correct

## Security Notes

- Rate limiting is active (5 polls/hour, 10 votes/minute per IP)
- SQL injection protection via prepared statements
- XSS protection via input sanitization
- File upload validation (images only, 5MB max)
- IP-based voting restriction

## Maintenance

### Manual Cleanup (Optional)

Run cleanup script manually:
```bash
php cron/cleanup_expired.php
```

### Automatic Cleanup (Recommended)

Set up a cron job or scheduled task to run cleanup hourly:
- Windows: Task Scheduler
- Linux/Mac: Crontab

## Next Steps

1. Test creating polls with images
2. Test voting functionality
3. Test expiration system
4. Test sharing links
5. Verify security features (rate limiting)

Enjoy using QuickPoll! 🎉


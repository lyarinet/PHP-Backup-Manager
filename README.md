# PHP Backup Manager

A comprehensive web-based backup management system with SQLite database, user authentication, and modern web interface. Successfully transformed from a bash script into a full-featured PHP web application.

<img width="1917" height="901" alt="image" src="https://github.com/user-attachments/assets/e288b1a3-b3d9-4034-8f70-f7a89a491150" />

## ✨ Key Features

### 🔐 Authentication & Security
- ✅ Session-based authentication with secure password hashing
- ✅ Role-based access control (admin/user)
- ✅ CSRF protection and secure session management
- ✅ Encrypted storage for sensitive configuration data
- ✅ Secure session cookies with proper configuration

### 💾 Backup Capabilities
- ✅ **File Backups**: Compress directories using tar+gzip
- ✅ **MySQL Backups**: Export databases using mysqldump with SSL support
- ✅ **PostgreSQL Backups**: Export databases using pg_dump with SSL support
- ✅ **Real-time Progress Tracking**: Live progress bars with status updates
- ✅ **Database Creation Statements**: MySQL backups include CREATE DATABASE and USE statements
- ✅ **Error Handling**: Comprehensive error logging and user feedback

### 📊 Dashboard & Monitoring
- ✅ Overview statistics (total backups, success rate, disk usage)
- ✅ Recent backup history with status indicators
- ✅ Real-time progress modals with auto-close functionality
- ✅ Backup file browser and download interface
- ✅ System status monitoring

### ⚙️ Configuration Management
- ✅ **Web-based Configuration Interface**: Easy-to-use forms for all backup types
- ✅ **Quick Setup**: Auto-select checkboxes for common configurations
- ✅ **Database Discovery**: Automatic detection of MySQL and PostgreSQL databases
- ✅ **Multiple Backup Profiles**: Support for unlimited configurations
- ✅ **Encrypted Credential Storage**: Secure storage of database passwords
- ✅ **Enable/Disable**: Individual configuration management
- ✅ **Path Security Management**: Global settings for allowed backup paths

### 🎯 Advanced Features
- ✅ **Progress Bar System**: Real-time backup progress with polling
- ✅ **Modal-based UI**: Modern Bootstrap 5 interface
- ✅ **Database Discovery**: Automatic detection of available databases
- ✅ **SSL/TLS Support**: Handles MySQL and PostgreSQL SSL connections
- ✅ **Error Recovery**: Comprehensive error handling and user feedback
- ✅ **Session Management**: Proper session handling for web and API calls
- ✅ **Global Path Management**: Web interface for managing allowed backup paths
- ✅ **Security Controls**: Configurable path restrictions with audit logging

## 🚀 Quick Start

### Prerequisites
- ✅ PHP 7.4 or higher (tested with PHP 8.4.11)
- ✅ SQLite support (PDO SQLite extension)
- ✅ OpenSSL extension
- ✅ Web server (Apache/Nginx) or PHP built-in server
- ✅ Command line tools: `tar`, `gzip`, `mysqldump`, `pg_dump`

### 🎯 One-Minute Setup

1. **Start the application**
   ```bash
   cd /var/www/html/backup_package
   php -S localhost:8080
   ```

2. **Access the web interface**
   - Navigate to: `http://localhost:8080/`
   - Default credentials: `admin` / `admin123`
   - **Important**: Change the default password immediately!

3. **Create your first backup**
   - Click "New Configuration"
   - Select backup type (Files, MySQL, or PostgreSQL)
   - Use Quick Setup for common configurations
   - Click "Run Backup" to test

4. **Configure path security (if needed)**
   - Navigate to Settings → Path Settings
   - Add allowed backup paths for your system
   - Use Quick Actions for common paths
   - Verify paths are working with test backups

### 🔧 Production Setup

1. **Set proper permissions**
   ```bash
   chmod 755 /var/www/html/backup_package
   chmod 600 /var/www/html/backup_package/.encryption_key
   chmod 600 /var/www/html/backup_package/backups.db
   ```

2. **Configure web server** (Apache/Nginx)
   - Point document root to `/var/www/html/backup_package`
   - Ensure PHP is enabled
   - Set up SSL/HTTPS for production

3. **Set up automated backups**
   ```bash
   # Add to crontab (run every hour)
   * * * * * /usr/bin/php /var/www/html/backup_package/cron.php
   ```

## 📁 File Structure

```
/var/www/html/backup_package/
├── 📄 index.php                 # Login page with authentication
├── 📊 dashboard.php            # Main dashboard with statistics
├── ⚙️ configurations.php       # Configuration management interface
├── 📋 history.php             # Backup history viewer
├── 🔧 settings.php            # System settings and user management
├── 📁 settings_paths.php      # Path security management interface
├── 🚪 logout.php              # Logout handler
├── 🔄 migrate.php             # Database migration script
├── ⏰ cron.php                # Scheduled task executor
├── ⚙️ config.php              # Application configuration
├── 📁 api/                    # REST API endpoints
│   ├── 🔄 backup.php         # Backup operations with progress tracking
│   ├── ⚙️ config.php         # Configuration management API
│   ├── 📋 history.php        # History management API
│   ├── 📊 progress.php       # Real-time progress API
│   └── 🔧 settings.php       # Settings management API
├── 📁 includes/               # Core PHP classes
│   ├── 🗄️ Database.class.php # SQLite wrapper with prepared statements
│   ├── 🔐 Auth.class.php     # Authentication and session management
│   ├── 💾 BackupManager.class.php # Core backup logic (Files, MySQL, PostgreSQL)
│   └── 🛠️ functions.php      # Helper functions and utilities
├── 📁 assets/                 # Frontend assets
│   ├── 🎨 css/style.css      # Bootstrap 5 stylesheet
│   └── ⚡ js/app.js          # JavaScript for dynamic interactions
├── 📁 logs/                   # Application logs
│   └── 📝 backup_manager.log # Backup execution logs
├── 🗄️ backups.db             # SQLite database
├── 🔑 .encryption_key        # Encryption key for sensitive data
└── 📖 README.md              # This documentation
```

## 🧭 Navigation Structure

The application features a consistent navigation menu across all pages:

- **🏠 Dashboard** - Main overview with statistics and recent backups
- **⚙️ Configurations** - Manage backup configurations (Files, MySQL, PostgreSQL)
- **📋 History** - View backup history and download files
- **🔧 Settings** - System settings and user management
- **📁 Path Settings** - Manage allowed backup paths for security

## ⚙️ Configuration

### 🗄️ Database Settings
- **SQLite Database**: Automatically created at `/var/www/html/backup_package/backups.db`
- **Encryption**: Sensitive data encrypted using OpenSSL
- **Permissions**: Database file secured with 600 permissions

### 💾 Backup Directory
- **Default**: `/tmp/backups` (writable by web server)
- **Configurable**: Change in Settings → General
- **Permissions**: Ensure web server has write access

### 🔐 Security Settings
- **Default Admin**: `admin` / `admin123` (change immediately!)
- **Session Security**: HTTP-only cookies, secure flags
- **File Permissions**: 600 for database, 755 for directories
- **HTTPS**: Required for production environments
- **Path Security**: Configurable allowed backup paths with web interface
- **Audit Logging**: All path changes and security events logged

### 🌐 Network Configuration
- **MySQL/PostgreSQL**: Supports SSL/TLS connections
- **Database Discovery**: Automatic detection of available databases
- **Error Handling**: Comprehensive connection error reporting

## 🔌 API Endpoints

### 🔄 Backup Operations
- `POST /api/backup.php` - Start backup with progress tracking
- `GET /api/progress.php?history_id=X` - Get real-time backup progress
- `GET /api/backup.php?action=list` - List all backups
- `DELETE /api/backup.php?action=delete&history_id=X` - Delete backup

### ⚙️ Configuration Management
- `GET /api/config.php?action=list` - List all configurations
- `POST /api/config.php` - Create new configuration
- `PUT /api/config.php` - Update existing configuration
- `DELETE /api/config.php?action=delete&config_id=X` - Delete configuration

### 📋 History & Monitoring
- `GET /api/history.php?action=list` - List backup history
- `GET /api/history.php?action=get&history_id=X` - Get backup details
- `GET /api/history.php?action=stats` - Get system statistics

### 🔍 Database Discovery
- `POST /api/discover.php` - Discover MySQL/PostgreSQL databases
- Automatic database detection with SSL support
- Timeout protection and error handling

### 🔒 Path Security Management
- **Web Interface**: Navigate to Settings → Path Settings
- **Add Paths**: Individual path addition with validation
- **Bulk Update**: Comma-separated path management
- **Quick Actions**: Common paths (Web Root, Home, Opt, Big Data)
- **Audit Logging**: All path changes tracked in activity logs
- **Real-time Validation**: Immediate path safety checking

## Cron Job Setup

### Method 1: Command Line
```bash
# Add to crontab
* * * * * /usr/bin/php /var/www/html/backup_package/cron.php
```

### Method 2: Web-based (with security key)
```bash
# Generate a secure cron key
openssl rand -hex 32

# Add to crontab
* * * * * curl -s "http://your-domain.com/backup_package/cron.php?cron_key=your-generated-key"
```

## Migration from Bash Script

If you have an existing bash backup script configuration:

1. **Run the migration script**:
   ```bash
   php migrate.php
   ```

2. **The migration will**:
   - Import configuration from `~/.backup_manager.conf`
   - Create backup configurations for files, MySQL, and PostgreSQL
   - Import existing backup files into the database
   - Preserve backup directory structure

3. **Verify the migration**:
   - Check the dashboard for imported configurations
   - Review backup history for existing files
   - Test running a backup manually

## Security Considerations

### File Permissions
```bash
# Set proper permissions
chmod 600 /var/www/html/backup_package/backups.db
chmod 600 /var/www/html/backup_package/.encryption_key
chmod 755 /var/www/html/backup_package
```

### Database Security
- Database credentials are encrypted using OpenSSL
- SQLite database file has restricted permissions (600)
- Prepared statements prevent SQL injection

### Session Security
- HTTP-only cookies
- Secure flag for HTTPS
- Session timeout (1 hour default)
- CSRF token validation

## 🛠️ Troubleshooting

### ✅ Common Issues & Solutions

1. **🔒 Permission Denied Errors**
   - **Solution**: Check file permissions on backup directory
   - **Fix**: `chmod 755 /tmp/backups` or configure writable directory
   - **Verify**: Web server user can write to backup location

2. **🗄️ Database Connection Issues**
   - **Solution**: Check SQLite extension is installed
   - **Fix**: `php -m | grep sqlite` to verify extension
   - **Verify**: Database file permissions (600)

3. **💾 Backup Failures**
   - **Solution**: Check command line tools are available
   - **Required**: `tar`, `gzip`, `mysqldump`, `pg_dump`
   - **Verify**: Database credentials and disk space

4. **🔐 SSL/TLS Connection Issues**
   - **MySQL**: Use `--skip-ssl` flag for non-SSL connections
   - **PostgreSQL**: Use `--set=sslmode=disable` for non-SSL connections
   - **Error**: "TLS/SSL error: self-signed certificate" - Use SSL bypass options

5. **📊 Progress Bar Issues**
   - **Solution**: Check browser console for JavaScript errors
   - **Fix**: Ensure session cookies are being sent
   - **Verify**: API endpoints are returning valid JSON

6. **🔒 Path Security Issues**
   - **Problem**: "Unsafe path" errors during backup
   - **Solution**: Use Path Settings interface to add allowed paths
   - **Fix**: Navigate to Settings → Path Settings → Add New Path
   - **Verify**: Check allowed paths in database settings

### 📝 Log Files
- **Application logs**: `/var/www/html/backup_package/logs/backup_manager.log`
- **Web server logs**: Check Apache/Nginx error logs
- **System logs**: `/var/log/syslog` or `/var/log/messages`
- **Debug logs**: Check browser developer console for JavaScript errors

## 🎯 Successfully Implemented Features

### ✅ **Core Functionality**
- **Authentication System**: Complete with session management and security
- **Backup Engine**: Files, MySQL, and PostgreSQL backup support
- **Progress Tracking**: Real-time progress bars with auto-close functionality
- **Database Discovery**: Automatic detection of MySQL and PostgreSQL databases
- **Configuration Management**: Web-based interface for all backup types
- **Error Handling**: Comprehensive error reporting and user feedback

### ✅ **Advanced Features**
- **SSL/TLS Support**: Handles MySQL and PostgreSQL SSL connections
- **Session Management**: Proper session handling for web and API calls
- **Encrypted Storage**: Sensitive data encrypted using OpenSSL
- **Real-time Updates**: AJAX polling for backup progress
- **Modal-based UI**: Modern Bootstrap 5 interface
- **Quick Setup**: Auto-select checkboxes for common configurations
- **Path Security Management**: Web interface for managing allowed backup paths
- **Global Settings**: Database-driven configuration for security controls

### ✅ **Database Schema**
The application uses the following main tables:
- `users` - User accounts and authentication
- `backup_configs` - Backup configuration profiles  
- `backup_history` - Backup execution history with progress tracking
- `backup_files` - Individual backup files
- `settings` - System configuration
- `activity_logs` - User activity audit trail

## 🚀 Production Ready

This PHP Backup Manager is **production-ready** with:
- ✅ **Complete functionality** - All features implemented and tested
- ✅ **Security measures** - Authentication, encryption, and session security
- ✅ **Error handling** - Comprehensive error reporting and recovery
- ✅ **User interface** - Modern, responsive web interface
- ✅ **API endpoints** - RESTful API for all operations
- ✅ **Documentation** - Complete setup and troubleshooting guide

## 📞 Support

For issues and questions:
1. ✅ Check the troubleshooting section above
2. ✅ Review log files for error messages  
3. ✅ Verify system requirements are met
4. ✅ Test with minimal configuration first

---

## 🆕 Recent Updates

### ✅ **Path Security Management (Latest)**
- **Global Path Settings**: Web interface for managing allowed backup paths
- **Database-Driven Configuration**: No code changes needed to add new paths
- **Quick Action Buttons**: Common paths (Web Root, Home, Opt, Big Data)
- **Audit Logging**: All path changes tracked in activity logs
- **Real-time Validation**: Immediate path safety checking
- **Bulk Operations**: Add/remove multiple paths efficiently

### ✅ **Enhanced Security**
- **Configurable Path Restrictions**: Prevent unauthorized directory access
- **Web-based Management**: Easy path administration without file editing
- **Activity Tracking**: Complete audit trail for security changes
- **Validation System**: Real-time path safety verification

**🎉 This is a fully functional, production-ready backup management system with advanced security controls!**

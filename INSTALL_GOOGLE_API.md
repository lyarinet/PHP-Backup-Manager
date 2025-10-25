# Google API Client Library Installation Guide

This guide explains how to install the Google API client library required for Google Drive integration.

## Option 1: Using Composer (Recommended)

### Step 1: Install Composer
If you don't have Composer installed:

```bash
# Download and install Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
```

### Step 2: Install Google API Client
```bash
# Navigate to your backup package directory
cd /var/www/html/backup_package

# Install the Google API client library
composer require google/apiclient
```

### Step 3: Update PHP Include Path
Add the Composer autoloader to your PHP configuration or include it in your files:

```php
// Add this to the top of files that use Google Drive
require_once 'vendor/autoload.php';
```

## Option 2: Manual Installation

### Step 1: Download the Library
```bash
# Download the latest release
wget https://github.com/googleapis/google-api-php-client/archive/v2.15.0.tar.gz
tar -xzf v2.15.0.tar.gz
mv google-api-php-client-2.15.0 google-api-php-client
```

### Step 2: Include in Your Project
```php
// Add this to your PHP files
require_once 'google-api-php-client/vendor/autoload.php';
```

## Option 3: System-wide Installation

### For Ubuntu/Debian:
```bash
sudo apt-get update
sudo apt-get install php-google-api-php-client
```

### For CentOS/RHEL:
```bash
sudo yum install php-google-api-php-client
```

## Verification

To verify the installation is working:

```bash
php -r "require_once 'vendor/autoload.php'; echo 'Google API client loaded successfully';"
```

## Troubleshooting

### Common Issues:

1. **"Class Google_Client not found"**
   - Ensure the autoloader is included
   - Check that the library is properly installed
   - Verify file permissions

2. **"Composer not found"**
   - Install Composer first
   - Add Composer to your PATH

3. **"Permission denied"**
   - Check file permissions
   - Ensure web server can read the files

### File Structure After Installation:
```
backup_package/
├── vendor/
│   └── autoload.php
├── includes/
│   └── storage/
│       └── GoogleDriveStorage.class.php
└── ...
```

## Next Steps

After installing the Google API client library:

1. Go to **Settings** > **Cloud Storage**
2. Add a new Google Drive provider
3. Configure your OAuth credentials
4. Test the connection

## Support

If you continue to have issues:

1. Check the PHP error logs
2. Verify the Google API client library is in the correct location
3. Ensure your PHP version is compatible (PHP 7.4+)
4. Check that all required PHP extensions are installed

For more information, visit the [Google API PHP Client documentation](https://github.com/googleapis/google-api-php-client).

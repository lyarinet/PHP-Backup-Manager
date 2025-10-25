# Google Drive Integration Setup Guide

This guide will help you set up Google Drive integration for the backup management system.

## Prerequisites

1. **Google Cloud Project**: You need a Google Cloud Project with billing enabled
2. **Google Drive API**: The Google Drive API must be enabled
3. **OAuth 2.0 Credentials**: You need to create OAuth 2.0 credentials
4. **PHP Google API Client**: Install the Google API client library

## Step 1: Create Google Cloud Project

1. Go to [Google Cloud Console](https://console.cloud.google.com/)
2. Create a new project or select an existing one
3. Enable billing for the project (required for API access)

## Step 2: Enable Google Drive API

1. In the Google Cloud Console, go to "APIs & Services" > "Library"
2. Search for "Google Drive API"
3. Click on "Google Drive API" and click "Enable"

## Step 3: Create OAuth 2.0 Credentials

1. Go to "APIs & Services" > "Credentials"
2. Click "Create Credentials" > "OAuth 2.0 Client IDs"
3. Choose "Web application" as the application type
4. Set the name (e.g., "Backup Manager")
5. Add authorized redirect URIs:
   - `https://yourdomain.com/backup_package/oauth_callback.php`
   - Replace `yourdomain.com` with your actual domain
6. Click "Create"
7. Copy the **Client ID** and **Client Secret** - you'll need these later

## Step 4: Install Google API Client Library

### Option A: Using Composer (Recommended)

```bash
composer require google/apiclient
```

### Option B: Manual Installation

1. Download the Google API client library
2. Extract it to your project directory
3. Include the autoloader in your PHP files

## Step 5: Configure in Backup Manager

1. Go to **Settings** > **Cloud Storage**
2. Click **Add Provider**
3. Select **Google Drive** as the provider type
4. Fill in the configuration:
   - **Provider Name**: Give it a descriptive name (e.g., "My Google Drive")
   - **Client ID**: Paste the Client ID from Step 3
   - **Client Secret**: Paste the Client Secret from Step 3
   - **Redirect URI**: Should be pre-filled with your callback URL
5. Click **Add Provider**

## Step 6: Configure OAuth Consent Screen (Important!)

Before you can authenticate, you need to configure the OAuth consent screen to allow your account access.

### 6.1 Navigate to OAuth Consent Screen

1. Go to [Google Cloud Console](https://console.cloud.google.com/)
2. Select your project
3. Navigate to the OAuth consent screen:
   - **Option A**: Click "Audience" in the left sidebar
   - **Option B**: Go to APIs & Services > OAuth consent screen
   - **Option C**: Use direct link: https://console.cloud.google.com/apis/credentials/consent

### 6.2 Add Test Users

1. On the OAuth consent screen page, scroll down to **"Test users"** section
2. Click **"Add Users"** or **"Add test users"**
3. Enter your email: `lyari.net@gmail.com`
4. Click **"Save"**

### 6.3 Verify User Type Settings

- Make sure your project is set to **"External"** user type for testing
- If you can't find the test users section, check that you have the right permissions
- Try refreshing the page after a few minutes if the section doesn't appear

## Step 7: Authenticate with Google Drive

1. After adding the provider and test user, you'll see an **Authenticate** button
2. Click **Authenticate** to start the OAuth flow
3. You'll be redirected to Google's authorization page
4. Sign in with your Google account (`lyari.net@gmail.com`)
5. Grant the requested permissions
6. You'll be redirected back to the backup manager
7. The authentication should complete automatically

## Step 8: Test the Connection

1. Click the **Test** button next to your Google Drive provider
2. You should see a success message with your Google account email
3. The system will also show your storage quota information

## Features Available

Once configured, Google Drive integration provides:

- **Automatic Upload**: Backups can be automatically uploaded to Google Drive
- **File Management**: View, download, and delete files from Google Drive
- **Storage Quota**: Monitor your Google Drive storage usage
- **Secure Access**: OAuth 2.0 authentication with token refresh

## Troubleshooting

### Common Issues

1. **"Google API client not available"**
   - Install the Google API client library (Step 4)

2. **"Invalid redirect URI"**
   - Ensure the redirect URI in Google Cloud Console matches your callback URL
   - Check that the URL is accessible from the internet

3. **"Access blocked: has not completed the Google verification process"**
   - This means you need to add yourself as a test user (Step 6)
   - Go to OAuth consent screen and add your email to test users
   - Make sure your project is set to "External" user type

4. **"Access blocked: request is invalid" with "redirect_uri_mismatch"**
   - Check that the redirect URI in Google Cloud Console exactly matches your callback URL
   - Common issues: missing `http://` or `https://`, wrong domain, or extra slashes
   - The redirect URI should be: `http://backup.lyaritech.com/oauth_callback.php` (or `https://` if using SSL)

5. **"Access token expired"**
   - The system should automatically refresh tokens
   - If not, re-authenticate using the Authenticate button

6. **"Insufficient permissions"**
   - Make sure you granted all requested permissions during OAuth
   - Re-authenticate if needed

7. **Can't find "Test users" section in OAuth consent screen**
   - Make sure you're on the correct page: https://console.cloud.google.com/apis/credentials/consent
   - Check that your project is set to "External" user type
   - Try refreshing the page after a few minutes
   - Ensure you have the right permissions in the Google Cloud project

### OAuth Scopes

The integration requests these permissions:
- `https://www.googleapis.com/auth/drive.file` - Upload and manage files created by the app
- `https://www.googleapis.com/auth/drive.metadata.readonly` - Read file metadata

## Security Notes

- **Client Secret**: Keep your client secret secure and never share it
- **Access Tokens**: Tokens are stored encrypted in the database
- **Refresh Tokens**: Used for automatic token renewal
- **Permissions**: The app only accesses files it creates (drive.file scope)

## API Limits

Google Drive API has the following limits:
- **Quota**: 1,000 requests per 100 seconds per user
- **File Size**: 5TB maximum file size
- **Rate Limits**: Vary by operation type

## Support

If you encounter issues:

1. Check the activity logs in the backup manager
2. Verify your Google Cloud Console configuration
3. Ensure the Google API client library is properly installed
4. Check that your server can make HTTPS requests to Google's servers

## Advanced Configuration

### Custom Redirect URI

If you need a custom redirect URI:

1. Update the redirect URI in Google Cloud Console
2. Update the redirect URI in your provider configuration
3. Ensure the callback URL is accessible

### Multiple Google Accounts

You can configure multiple Google Drive providers for different accounts:

1. Create separate OAuth credentials for each account
2. Configure separate providers in the backup manager
3. Each provider will authenticate with its respective Google account

### Folder Organization

Files uploaded to Google Drive will be stored in the root folder by default. For better organization:

1. Create folders in Google Drive manually
2. Update the remote path in your backup configurations
3. Files will be uploaded to the specified folder structure

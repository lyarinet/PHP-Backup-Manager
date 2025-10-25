# Google Drive Authentication Guide

## Current Status
Your Google Drive provider is configured with OAuth credentials, but the authentication flow hasn't been completed yet. You need to complete the OAuth authentication to get an access token.

## Steps to Complete Authentication

### 1. Go to Cloud Storage Settings
- Navigate to **Settings** > **Cloud Storage** in your backup manager
- Find your Google Drive provider in the list

### 2. Click the "Authenticate" Button
- Look for the **"Authenticate"** button next to your Google Drive provider
- Click it to start the OAuth flow

### 3. Complete Google OAuth Flow
- You'll be redirected to Google's authorization page
- Sign in with your Google account
- Grant the requested permissions for the backup manager
- You'll be redirected back to the backup manager

### 4. Test the Connection
- After authentication, click the **"Test"** button
- You should see a success message with your Google account email
- The system will also show your storage quota information

## What Happens During Authentication

1. **OAuth Initiation**: The system generates a Google OAuth URL with your client credentials
2. **User Authorization**: You authorize the backup manager to access your Google Drive
3. **Token Exchange**: Google provides an access token and refresh token
4. **Token Storage**: The tokens are securely stored in the database
5. **Connection Test**: The system verifies the connection works

## Troubleshooting

### "Authenticate" Button Not Visible
- Make sure your Google Drive provider has both Client ID and Client Secret configured
- Check that the provider is enabled

### OAuth Flow Fails
- Verify your redirect URI in Google Cloud Console matches: `https://backup.lyaritech.com/oauth_callback.php`
- Ensure your Google Cloud Project has the Google Drive API enabled
- Check that your OAuth credentials are correct

### "Invalid redirect URI" Error
- Go to [Google Cloud Console](https://console.cloud.google.com/)
- Navigate to "APIs & Services" > "Credentials"
- Edit your OAuth 2.0 Client ID
- Add the correct redirect URI: `https://backup.lyaritech.com/oauth_callback.php`

### Connection Test Still Fails After Authentication
- Try clicking "Authenticate" again to refresh the tokens
- Check the activity logs for more detailed error messages
- Verify your Google account has sufficient storage space

## Security Notes

- **Access Tokens**: Automatically refresh when they expire
- **Permissions**: The app only accesses files it creates (secure scope)
- **Token Storage**: Tokens are encrypted and stored securely
- **Revocation**: You can revoke access in your Google account settings

## Next Steps After Authentication

Once authentication is complete:

1. **Test Connection**: Verify the connection works
2. **Configure Backup**: Set up automatic cloud uploads for your backups
3. **Monitor Usage**: Check your Google Drive storage usage
4. **Manage Files**: View and manage uploaded backup files

## Support

If you continue to have issues:

1. Check the activity logs in the backup manager
2. Verify your Google Cloud Console configuration
3. Ensure your server can make HTTPS requests to Google's servers
4. Contact support with specific error messages from the logs

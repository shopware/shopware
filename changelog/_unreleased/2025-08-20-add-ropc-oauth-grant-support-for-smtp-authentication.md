---
title: Add ROPC OAuth grant support for SMTP authentication
issue: #11675
---
# Core
* Added Resource Owner Password Credentials (ROPC) grant support to `SmtpOauthTokenProvider` for OAuth2 SMTP authentication
* Added new system configuration options:
    * `core.mailerSettings.oauthGrantType` - OAuth grant type selection (client_credentials or password)
    * `core.mailerSettings.oauthUsername` - OAuth username for ROPC grant
    * `core.mailerSettings.oauthPassword` - OAuth password for ROPC grant
* Changed `SmtpOauthTokenProvider::fetchToken()` to support multiple grant types
* Added constants `GRANT_TYPE_CLIENT_CREDENTIALS` and `GRANT_TYPE_ROPC` in `SmtpOauthTokenProvider`
___
# Administration
* Added OAuth grant type selection dropdown to SMTP OAuth configuration
* Added conditional field display based on selected grant type:
    * Client Credentials: shows client ID, client secret, and scope fields
    * ROPC: shows username and password fields for OAuth authentication
* Added new computed properties in `sw-settings-mailer-smtp` component:
    * `isClientCredentials` - detects client credentials grant type
    * `isROPC` - detects ROPC grant type  
    * `oauthGrantTypeOptions` - provides grant type selection options
* Changed default mailer settings to include OAuth grant type configuration
* Added new template blocks:
    * `sw_settings_mailer_smtp_oauth_grant_type`
    * `sw_settings_mailer_smtp_oauth_username` 
    * `sw_settings_mailer_smtp_oauth_password`
___
# Upgrade Information
## SMTP OAuth2 Support for Resource Owner Password Credentials
The mailer configuration now also supports SMTP OAuth2 authentication using Resource Owner Password Credentials (ROPC) grant. This is especially important when you use Microsoft 365 as SMTP provider as ROPC is the default OAuth flow there.

To set it up, follow these steps:
1. Go to Settings > System > Mailer
2. Select "SMTP server with OAuth2" as email agent
3. Choose "Resource Owner Password Credentials (ROPC)" as OAuth grant type
4. Enter your OAuth token URL, username and password
5. Test the connection


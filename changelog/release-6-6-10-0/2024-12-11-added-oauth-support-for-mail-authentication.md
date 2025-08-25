---
title: Added Oauth support for mail authentication
issue: NEXT-18106
author: Jozsef Damokos
author_email: j.damokos@shopware.com
author_github: @jozsefdamokos
---
# Core
* Added new option to use SMTP for mail sending with OAuth2 authentication
___
# Administration
* Added new settings option "SMTP server with OAuth2" to Setting > System > Mailer
___
# Upgrade Information
## Mail settings
If you are using OAuth2 authentication for mail sending, you need to update your mail settings in the administration:
1. Go to Setting > System > Mailer
2. Select the new option "SMTP server with OAuth2"
3. Fill in the required fields
4. Save the settings
5. Test the connection
6. Check Settings > System > Event logs for any errors

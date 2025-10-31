---
title: Use staging config and mail delivery flag for mail delivery check
author: Marcus Müller
author_email: 25648755+M-arcus@users.noreply.github.com
author_github: @M-arcus
---
# Core
* Changed the test mode check in `MailService` from the general staging config to both the flag shopware.staging.mailing.disable_delivery and the staging config setting
___
# Upgrade Information
## Staging configuration
The check for the test mode in `MailService` now checks for both the Staging Mode `core.staging` and the `shopware.staging.mailing.disable_delivery` configuration.

---
title: Use specific staging config check for mail delivery
author: Marcus Müller
author_email: 25648755+M-arcus@users.noreply.github.com
author_github: @M-arcus
---
# Core
* Changed the test mode check in `MailService` from the general staging flag to the flag `shopware.staging.mailing.disable_delivery`

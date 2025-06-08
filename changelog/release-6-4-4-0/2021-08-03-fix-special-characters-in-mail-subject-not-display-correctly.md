---
title: Fix special characters in mail subject not display correctly
issue: NEXT-13071
---
# Core
* Changed `MailService::send` function at `Shopware\Core\Content\Mail\Service` to decode special characters in email subject and sender name.

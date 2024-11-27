---
title: Fix sending mail in test mode
issue: NEXT-00000
author: Moritz Müller
author_email: moritz@momocode.de
author_github: @momocode-de
---
# Core
* Changed method `send` in `Shopware\Core\Content\Mail\Service\MailService` class so that no CRITICAL error occurs if no salesChannelId is available and you are in test mode

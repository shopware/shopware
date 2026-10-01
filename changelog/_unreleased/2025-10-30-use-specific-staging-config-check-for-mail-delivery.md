---
title: Use staging config and staging mail delivery flag for mail delivery check
author: Marcus Müller
author_email: 25648755+M-arcus@users.noreply.github.com
author_github: @M-arcus
---
# Core
* Changed the disabled delivery check in `MailSender`. In Staging Mode `core.staging`, the `shopware.staging.mailing.disable_delivery` configuration is applied. Regardless of Mode the config setting `shopware.mailing.disable_delivery` always allows to disable mail delivery.
___
# Upgrade Information
## Staging configuration
The disabled delivery check in `MailSender` now checks for the Staging Mode `core.staging`, the `shopware.staging.mailing.disable_delivery` configuration and the config setting `shopware.mailing.disable_delivery`. Regardless of Mode the config setting `shopware.mailing.disable_delivery` always allows to disable mail delivery.

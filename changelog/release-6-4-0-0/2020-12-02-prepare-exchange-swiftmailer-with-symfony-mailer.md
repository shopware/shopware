---
title:              Prepare Exchange SwiftMailer with Symfony mailer
issue:              NEXT-12246
author:             Stefan Sluiter
author_email:       s.sluiter@shopware.com
author_github:      @ssltg
---
# Core
* Deprecated `Shopware\Core\Content\MailTemplate\Service\MailSender`
* Deprecated `Shopware\Core\Content\MailTemplate\Service\MailSenderInterface`
* Deprecated `Shopware\Core\Content\MailTemplate\Service\MailService`
* Deprecated argument `mailService` in `Shopware\Core\Content\MailTemplate\Subscriber\MailSendSubscriber`
* Deprecated method `createMessage` in `Shopware\Core\Content\MailTemplate\Service\MessageFactory` use `createMail` instead
___
# Upgrade Information
## prepare the exchange of Swift_Mailer with Symfony/Mailer in 6.4.0
We will exchange the current default mailer `Swift_Mailer` with the `Symfony\Mailer` in 6.4.0.
If this concerns your own code changes, you can already implement your changes behind this feature flag to minimize your work on the release of the 6.4.0. Please refer to [feature flag handling on docs.shopware.com](https://docs.shopware.com/en/shopware-platform-dev-en/references-internals/core/feature-flag-handling) about the handling of feature flags.

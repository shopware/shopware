---
title:              Exchange SwiftMailer with Symfony mailer
issue:              NEXT-12246
flag:               FEATURE_NEXT_12246
author:             Stefan Sluiter
author_email:       s.sluiter@shopware.com
author_github:      @ssltg
---
# Core
* Added `symfony/mailer ~4.4` to composer.json
* Added `Shopware\Core\Content\MailTemplate\Service\EmailSender`
* Added `Shopware\Core\Content\MailTemplate\Service\EmailService`
* Added `Shopware\Core\Content\MailTemplate\Service\AbstractEmailSender`
* Added `Shopware\Core\Content\MailTemplate\Service\AbstractEmailService`
* Added `Shopware\Core\Content\MailTemplate\Service\AbstractMessageFactory`
* Added `Shopware\Core\Content\MailTemplate\Service\AbstractMessageFactory`
* Added `Shopware\Core\Framework\Feature\Exception\FeatureActiveException`
* Added argument `emailService` with type `Shopware\Core\Content\MailTemplate\Service\EmailService` in `Shopware\Core\Content\MailTemplate\Subscriber\MailSendSubscriber`

* Changed argument type of argument `$message` in `Shopware\Core\Content\MailTemplate\Service\Event\MailBeforeSentEvent` from `Swift_Message` to `Symfony\Component\Mime\Email`
* Changed return type of method `getMessage` in `Shopware\Core\Content\MailTemplate\Service\Event\MailBeforeSentEvent` from `Swift_Message` to `Symfony\Component\Mime\Email`
* Changed `Shopware\Core\Content\MailTemplate\Service\MessageFactory` to extend from `Shopware\Core\Content\MailTemplate\Service\AbstractMessageFactory`

* Moved `Shopware\Core\Framework\Feature\FeatureNotActiveException` to `Shopware\Core\Framework\Feature\Exception\FeatureNotActiveException`

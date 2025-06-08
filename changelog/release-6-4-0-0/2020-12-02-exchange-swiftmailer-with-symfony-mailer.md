---
title:              Exchange SwiftMailer with Symfony mailer
issue:              NEXT-12246
author:             Stefan Sluiter
author_email:       s.sluiter@shopware.com
author_github:      @ssltg
---
# Core
* Added `symfony/mailer ~4.4` to composer.json
* Added `Shopware\Core\Content\Mail\Service\MailSender`
* Added `Shopware\Core\Content\Mail\Service\MailService`
* Added `Shopware\Core\Content\Mail\Service\MailerTransportFactory`
* Added `Shopware\Core\Content\Mail\Service\AbstractMailSender`
* Added `Shopware\Core\Content\Mail\Service\AbstractMailService`
* Added `Shopware\Core\Framework\Feature\Exception\FeatureActiveException`
* Added argument `emailService` with type `Shopware\Core\Content\Mail\Service\AbstractMailService` in `Shopware\Core\Content\MailTemplate\Subscriber\MailSendSubscriber`

* Changed argument type of argument `$message` in `Shopware\Core\Content\MailTemplate\Service\Event\MailBeforeSentEvent` from `Swift_Message` to `Symfony\Component\Mime\Email`
* Changed return type of method `getMessage` in `Shopware\Core\Content\MailTemplate\Service\Event\MailBeforeSentEvent` from `Swift_Message` to `Symfony\Component\Mime\Email`
* Changed `Shopware\Core\Framework\Feature\FeatureNotActiveException` to `Shopware\Core\Framework\Feature\Exception\FeatureNotActiveException`

* Removed `Shopware\Core\Content\MailTemplate\Service\MailSender`
* Removed `Shopware\Core\Content\MailTemplate\Service\MailSenderInterface`
* Removed `Shopware\Core\Content\MailTemplate\Service\MailService`
* Removed `Shopware\Core\Content\MailTemplate\Service\MailServiceInterface`
* Removed `Shopware\Core\Content\MailTemplate\Service\MessageFactoryInterface`
* Removed `Shopware\Core\Content\MailTemplate\Service\MessageFactory`
* Removed `Shopware\Core\Content\MailTemplate\Service\MessageTransportFactoryInterface`
* Removed `Shopware\Core\Content\MailTemplate\Service\MessageTransportFactory`
* Removed `Shopware\Core\Content\MailTemplate\Service\MailerTransportFactory`
* Removed `Shopware\Core\Content\MailTemplate\Service\MailerTransportFactoryInterface`
* Removed argument `mailService` in `Shopware\Core\Content\MailTemplate\Subscriber\MailSendSubscriber`
* Removed method `createMessage` in `Shopware\Core\Content\MailTemplate\Service\MessageFactory` use `createMail` instead
___
# Administration
* Removed block `sw_settings_mailer_smtp_authentication`
* Removed method `authenticationOptions` in component `sw-settings-mailer-smtp`


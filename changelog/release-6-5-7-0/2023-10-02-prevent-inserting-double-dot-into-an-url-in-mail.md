---
title: Prevent inserting double dots into an url in mail
issue: NEXT-23795
---
# Core
* Changed method `\Shopware\Core\Content\Mail\Service\MailerTransportLoader::getSendMailCommandLineArgument` to add `-i` option to the sendmail command to prevent double dot in url issue
* Added a new migration `Migration1696262484AddDefaultSendMailOptions` to add `-i` option in `core.mailerSettings.sendMailOptions` system_config if its not modified 
* Added a new domain exception `\Shopware\Core\Content\Mail\MailException`
___
# Administration
* Changed computed property `emailSendmailOptions` in component `sw-settings-mailer` to add `-i` option to default async mail dispatch option
* Changed computed property `emailSendmailOptions` in component `sw-first-run-wizard-mailer-local` to add `-i` option to default async mail dispatch option
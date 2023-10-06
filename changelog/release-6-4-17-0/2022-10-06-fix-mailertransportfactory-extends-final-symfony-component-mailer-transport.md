---
title: Fix MailerTransportFactory extends @final Symfony\Component\Mailer\Transport
issue: NEXT-23245
---
# Core
* Deprecated `MailerTransportFactory` class. It will be removed since v6.5.0.0
* Add new `MailerTransportLoader` class to load the mailer which is configured in the admin panel, otherwise the original factory from Symfony will be called.

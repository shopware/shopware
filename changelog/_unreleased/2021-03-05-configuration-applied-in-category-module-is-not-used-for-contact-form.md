---
title: Configuration applied in category module is not used for contact form
issue: NEXT-13722
---
# Core
* Added navigationId param to `store-api.contact.form` route and updated to send mail to the recipient addresses if it exists
* Changed `sendMail` method of `MailSendSubscriber` to send mail to the recipient addresses of MailStruct if it exists

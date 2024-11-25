---
title: Improve mail sender by using the private file system over message queue
issue: NEXT-00000
author: Benjamin Wittwer
author_email: benjamin.wittwer@a-k-f.de
author_github: akf-bw
---
# Core
* Changed `src/Core/Content/Mail/Service/AbstractMailSender.php` to deprecate the `envelope` parameter
* Changed `src/Core/Content/Mail/Service/MailSender.php` to no longer directly send the mail to the symfony mailer, but to write the serialized mail to the private file system & dispatch a `SendMailMessage` to the message bus
* Added `src/Core/Content/Mail/Message/SendMailMessage.php` to hold the `mailDataPath` on the message bus
* Added `src/Core/Content/Mail/Message/SendMailHandler.php` to consume the `SendMailMessage`, send the mail & delete the serialized mail from the private file system 

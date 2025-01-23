---
title: Send small mail object directly without filesystem access
author: Benjamin Wittwer
author_email: benjamin.wittwer@a-k-f.de
author_github: akf-bw
---
# Core
* Changed `Shopware\Core\Content\Mail\Service\MailSender` to send a mail directly to the mailer without using the file system if the mail object is already small enough for the message queue

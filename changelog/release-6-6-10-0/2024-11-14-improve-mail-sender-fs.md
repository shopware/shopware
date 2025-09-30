---
title: Improve mail sender by using the private file system over message queue
issue: NEXT-00000
author: Benjamin Wittwer
author_email: benjamin.wittwer@a-k-f.de
author_github: akf-bw
---
# Core
* Deprecated the `envelope` parameter in `Shopware\Core\Content\Mail\Service\AbstractMailSender::send`
* Changed `\Shopware\Core\Content\Mail\Service\MailSender` to write the serialized mail to the private file system and dispatch a `\Shopware\Core\Content\Mail\Message\SendMailMessage` to the message bus instead of directly sending the mail to the Symfony mailer

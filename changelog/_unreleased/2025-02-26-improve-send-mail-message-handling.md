---
title: Improve SendMailMessage handling with transport target
author: Benjamin Wittwer
author_email: benjamin.wittwer@a-k-f.de
author_github: akf-bw
---
# Core
* Changed `SendMailMessage` to no longer implement the `AsyncMessageInterface` to allow a better redirect / rounting of the message
* Added `'Shopware\Core\Content\Mail\Message\SendMailMessage': async` to the framework messenger routing

---
title: Changed logic for joining a header and footer to an email
issue: NEXT-32942
author: Simon Fiebranz
author_email: s.fiebranz@shopware.com
author_github: CR0YD
---
# Core
* Changed the criteria for getting the correct sales channel in the `send` method of `MailService.php`. When there is valid `SalesChannelEntity` in the parameter `templateData` that sales channel is used. If there is none, but a sales channel id is set in the `data` parameter, the corresponding sales channel is loaded from the repository. That is also always the case when the test mode is active, regardless of a possible `SalesChannelEntity` in `templateData`. But when also that does apply a sales channel isn't loaded.
___
# Administration
* Added loading of email header and footer html content for the corresponding sales channel to the content loading in `sw-order-send-document-modal`

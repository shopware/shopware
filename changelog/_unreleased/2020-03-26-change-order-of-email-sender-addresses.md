---
title: Change order of email sender addresses
author: Paul von Allwörden
author_email: paul.von.allwoerden@pickware.de
author_github: paulvonallwoerden
---
# Core
*  Changed method `getSender()` in `Core/Content/Mail/Service/MailService.php` to prioritize the sender address from `core.mailerSettings.senderAddress`.

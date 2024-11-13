---
title: Add `shopware.messenger.skip_enforce_size_messages` config option
issue: NEXT-00000
author: Benjamin Wittwer
author_email: benjamin.wittwer@a-k-f.de
author_github: akf-bw
---
# Core
* Added the `shopware.messenger.skip_enforce_size_messages` array config option which contains the `Symfony\Component\Mailer\Messenger\SendEmailMessage` message by the shopware config
* Changed `src/Core/Framework/MessageQueue/Subscriber/MessageQueueSizeRestrictListener.php` to skip over messages included in the `shopware.messenger.skip_enforce_size_messages` config option

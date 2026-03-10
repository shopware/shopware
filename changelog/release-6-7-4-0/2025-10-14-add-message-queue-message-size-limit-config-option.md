---
title: Add message queue message size limit config option
author: Benjamin Wittwer
author_email: Discord.Benjamin@web.de
author_github: gecolay
---
# Core
* Added `shopware.messenger.message_max_kib_size` config option to define the maximum size (in KiB) of messages in the message queue. Set to `0` to disable the size check. Defaults to `1024` KiB (1 MiB).

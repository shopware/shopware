---
title: Allow `association_fields` of `media_default_folder` to be nullable
issue: NEXT-0000
author: Max
author_email: max@swk-web.com
author_github: @aragon999
---
# Core
* Changed deprecated column `association_fields` of `media_default_folder` to be nullable, such that new default folders can be created even though the destructive migrations have not been executed

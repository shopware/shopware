---
title: Fix download link mail
issue: NEXT-00000
author: Elias Lackner
author_email: lackner.elias@gmail.com
author_github: @lacknere
---
# Core
* Changed `downloads_delivery` mail fixtures to use `rawUrl` instead of `url` for product download link to use the correct sales channel.
* Added new migration `Migration1701688920FixDownloadLinkMail` to update `downloads_delivery` mail templates.

---
title: Add new console command to replace the URL of a sales channel
author: Marcus Müller
author_email: 25648755+M-arcus@users.noreply.github.com
author_github: @M-arcus
---
# Core
* Added new console command `sales-channel:replace:url` to replace the url of a sales channel
* Changed `sales-channel:update:domain` to filter out headless channels earlier
___
# Upgrade Information
## Sales Channel Replace URL Command
A new `sales-channel:replace:url` command was added to replace the url of a sales channel.
```bash
bin/console sales-channel:replace:url <previous_url> <new_url>
```

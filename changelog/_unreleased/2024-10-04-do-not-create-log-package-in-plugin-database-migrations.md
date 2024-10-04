---
title: Do not create log package in plugin database migrations
issue: NEXT-0000
author: Max
author_email: max@swk-web.com
author_github: @aragon999
---
# Core
* Changed `database:create-migration` command to not create `#[Package('%%package%%')]` attribute on migration

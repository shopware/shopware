---
title: Use searchIds for import id resolving
issue: NEXT-0000
author: Max
author_email: max@swk-web.com
author_github: @aragon999
---
# Core
* Changed import serializers to only use `EntityRepository::searchIds` instead of `EntityRepository::search` for resolving ids

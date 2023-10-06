---
title: Fix double cart prefix on delete in redis cache adapter
issue: NEXT-21987
author: Micha Hobert
author_email: info@the-cake-shop.de
author_github: Isengo1989
---
# Core
* Removed `self::PREFIX` from `$this->delete()` because the prefix is already added in the delete method
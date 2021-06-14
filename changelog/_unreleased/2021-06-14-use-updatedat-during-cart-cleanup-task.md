---
title: Use updatedAt during cart cleanup task.
issue: NEXT-15718
author: Andreas Allacher
author_email: andreas.allacher@massiveart.com
author_github: @AndreasA
---
# Core
* Changed cleanup cart scheduled task to take `updated_at` into account.
* Added compound index to `cart` table for `updated_at`.

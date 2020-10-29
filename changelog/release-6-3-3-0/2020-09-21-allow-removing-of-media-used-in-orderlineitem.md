---
title: Allow removing of media used in orderLineItem
issue: NEXT-9981
---
# Core
* Changed foreign key of `order_line_item.cover_id` to `ON DELETE SET NULL`

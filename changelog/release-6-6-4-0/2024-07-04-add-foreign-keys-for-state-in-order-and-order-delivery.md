---
title: Add foreign keys for state in order and order delivery
issue: NEXT-37107
author: Max Stegmeyer
author_email: m.stegmeyer@shopware.com
---
# Core
* Added foreign key on `state_id` in table `order`. All invalid values are restored to initial state id
* Added foreign key on `state_id` in table `order_delivery`. All invalid values are restored to initial state id


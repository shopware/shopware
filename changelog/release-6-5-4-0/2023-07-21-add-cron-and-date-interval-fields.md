---
title: Add cron and date interval fields
issue: NEXT-29451
author: Max Stegmeyer
author_email: m.stegmeyer@shopware.com
---
# Core
* Added `Shopware\Core\Framework\DataAbstractionLayer\Field\CronIntervalField`, which can be used to describe intervals in a cron-like syntax.
* Added `Shopware\Core\Framework\DataAbstractionLayer\Field\DateIntervalField`, which can be used to describe intervals in a PHP DateInterval-like syntax.

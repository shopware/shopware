---
title: Check mixed case SQL keywords in master slave check
author: Joshua Behrens
author_email: code@joshua-behrens.de
author_github: @JoshuaBehrens
issue: NEXT-16629
---
# Core
*  Added case insensitivity flag to regex in `\Shopware\Core\Profiling\Doctrine\DebugStack` to check for non-uppercased SQL statements

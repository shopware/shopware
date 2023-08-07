---
title: Fix support for Symfonys `trusted_*` kernel parameters
issue: NEXT-29302
author: Christian Schiffler
author_email: c.schiffler@cyberspectrum.de
author_github: discordier
---
# Core
* Changed `\Shopware\Core\Kernel::boot()` to support for Symfonys trusted_* kernel parameters that were previously ignored.

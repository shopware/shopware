---
title: Fixed the error handler callback of the CLI Updater to be PHP8 compatible
issue: NEXT-20578
author: Felix von WIRDUZEN
author_email: felix@wirduzen.de
author_github: wirduzen-felix
---
# Core
* Removed the 5th argument from the `set_error_handler()` callback in `Shopware\Recovery\Update\Console\Application` to be PHP8 compatible

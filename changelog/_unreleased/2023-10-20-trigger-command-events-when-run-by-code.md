---
title: Trigger Symfony command events, when commands are run by code
author: Joshua Behrens
author_email: code@joshua-behrens.de
author_github: @JoshuaBehrens
---
# Core
* Changed sub command invocation within `system:install`, `system:setup` and `system:update:finish` from direct command service usage to running through Symfony application to trigger Symfony command events

---
title: Upgrade composer/composer and symfony/runtime dependencies
issue: NEXT-31639
---

# Core
* Changed the required version of composer/composer to minimum 2.7, to be fully compatible with symfony 7, see https://github.com/composer/composer/issues/11736
* Removed compatibility with symfony/console 6.4, after composer upgrade, so all symfony dependencies are now on version 7

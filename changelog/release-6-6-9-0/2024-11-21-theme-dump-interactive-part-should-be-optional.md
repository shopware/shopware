---
title: theme:dump interactive part should be optional
issue: NEXT-39724
author: Björn Meyer
author_email: b.meyer@shopware.com
author_github: @BrocksiNet
---
# Storefront
* Changed `ThemeDumpCommand.php` so it can also run without the interactive part. This is useful for CI/CD pipelines.
  * Without interaction `bin/console theme:dump -n`
  * With interaction questions `bin/console theme:dump` (can throw error if domain is not found)
  * With provided arguments `bin/console theme:dump 0192ddc89aee73e99ecc828b5e667e5c https://localhost:2000` see usage `theme:dump [<theme-id> [<domain-url>]]`

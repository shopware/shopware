---
title: Fix theme settings getting lost when navigating quickly
issue: #9572
author: Alexander Menk
author_email: a.menk@imi.de
author_github: @amenk
---

# Storefront
* Added new parameter `reset` to `\Shopware\Storefront\Theme\Controller\ThemeController::updateTheme()` which resets the theme.
* Changed call in `sw-theme-manager-detail` to use the `reset` flag to run a reset and update in one step.

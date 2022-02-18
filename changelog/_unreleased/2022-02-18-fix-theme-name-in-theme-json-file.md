---
title: Fix theme name in theme json file
issue: 2257
author: Mohab E.
author_email: mohab.elsheikh@gmail.com
author_github: mohabmes
---
# Storefront
* Changed the `$themeName` in `Theme/Command/ThemeCreateCommand.php` to `$pluginName` just to match the theme directory name created (if the theme name is lowercase). 

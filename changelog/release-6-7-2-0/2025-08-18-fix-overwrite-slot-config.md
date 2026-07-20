---
title: fix overwrite slot config
issue: 11800
---
# Core
* Changed the way CMS slot configurations are overwritten to ensure that list-type configurations (like product collections) are completely replaced rather than merged, providing more intuitive behavior when customizing CMS elements in `SalesChannelCmsPageLoader.php`.
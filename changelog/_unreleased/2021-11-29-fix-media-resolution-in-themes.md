---
title: Fix media resolution in themes 
issue: NEXT-19048
author: Niklas Limberg
author_github: NiklasLimberg
author_email: n.limberg@shopware.com
---
# Storefront
* Changed `Shopware\Storefront\Theme\ConfigLoader\DatabaseConfigLoader::resolveMediaIds` to fill the correct property in the theme config
___
# Upgrade Information
## Media Resolution in Themes
Media URLs are now available in the property path `$config[$key]['value']` instead of `$config['fields'][$key]['value'] = $media->getUrl();`, where for example scss expected them to be.


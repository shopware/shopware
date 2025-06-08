---
title: Categories with external link shouldnt have SEO urls
issue: NEXT-10719
author: Niklas Limberg
author_email: n.limberg@shopware.com
author_github: NiklasLimberg
---
# Storefront
* Changed `Shopware\Storefront\Framework\Seo\SeoUrlRoute\NavigationPageSeoUrlRoute::prepareCriteria()` to filter categories of type `CategoryDefinition::TYPE_FOLDER` and linkType `CategoryDefinition::LINK_TYPE_EXTERNAL`

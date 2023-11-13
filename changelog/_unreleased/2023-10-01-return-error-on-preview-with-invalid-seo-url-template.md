---
title: Return error on preview with invalid SEO URL template
author: Joshua Behrens
issue: NEXT-30828
author_email: code@joshua-behrens.de
author_github: @JoshuaBehrens
---
# Core
* Added class `\Shopware\Core\Content\Seo\ConfiguredSeoUrlRoute` to allow passing `\Shopware\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteInterface` with an altered configuration 
___
# API
* Changed `/api/_action/seo-url-template/preview` to also return `FRAMEWORK__INVALID_SEO_TEMPLATE` 
___
# Administration
* Changed SEO URL validation with Admin API changes to flag SEO URL templates with invalid property reference as invalid

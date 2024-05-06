---
title: Fix issues by statefulness twig environment in SeoUrlGenerator
issue: NEXT-30706
author: JoshuaBehrens
author_email: code@joshua-behrens.de
author_github: @JoshuaBehrens
---
# Core
* Changed usage of broken twig templates in `Shopware\Core\Content\Seo\SeoUrlGenerator` to bullet-proof it against invalid configured SEO URL templates
* Changed used execution time when using `Shopware\Core\Content\Seo\SeoUrlGenerator` with a broken SEO URL template as a broken twig will not be used anymore to try to generate URLs
* Changed internal template name for template from string `Shopware\Core\Content\Seo\SeoUrlGenerator` to ensure Twig caching can rely on template name
* Added logging to twig usage errors in `Shopware\Core\Content\Seo\SeoUrlGenerator`

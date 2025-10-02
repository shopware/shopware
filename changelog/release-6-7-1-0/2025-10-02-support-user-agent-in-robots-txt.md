---
title: Support User-agent and other directives in robots.txt configuration
issue: 12787
author: Claude
author_email: noreply@anthropic.com
author_github: @anthropics
---
# Storefront
* Added support for all robots.txt directives (User-agent, Crawl-delay, etc.) in `Shopware\Storefront\Page\Robots\Struct\DomainRuleStruct`
___
# Upgrade Information

## robots.txt Configuration Now Supports All Directives

Previously, the configurable robots.txt feature only parsed `Allow` and `Disallow` directives, ignoring other valid directives like `User-agent` and `Crawl-delay`.

Now you can use all standard robots.txt directives in your configuration:

```
User-agent: Googlebot
Disallow: /private/

User-agent: Bingbot
Crawl-delay: 10
Allow: /
```

Path-based directives (`Allow` and `Disallow`) continue to work with the domain base path as before, while other directives are rendered as-is without path modification.

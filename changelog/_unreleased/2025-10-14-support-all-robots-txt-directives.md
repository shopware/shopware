---
title: Support all robots.txt directives including User-agent blocks
issue: NEXT-12787
author: Björn Meyer
author_email: b.meyer@shopware.com
author_github: BrocksiNet
---
# Core
* Added `Shopware\Storefront\Page\Robots\Parser\RobotsDirectiveParser` to parse robots.txt configurations
* Added `Shopware\Storefront\Page\Robots\Parser\ParsedRobots` value object for parsed robots.txt data
* Added `Shopware\Storefront\Page\Robots\Struct\RobotsDirective` value object representing a single directive
* Added `Shopware\Storefront\Page\Robots\Struct\RobotsDirectiveType` enum for directive type constants
* Added `Shopware\Storefront\Page\Robots\Struct\RobotsUserAgentBlock` value object representing a user-agent block
* Added `globalUserAgentBlocks` property to `Shopware\Storefront\Page\Robots\RobotsPage` to store global user-agent blocks
* Changed `Shopware\Storefront\Page\Robots\RobotsPageLoader` to parse and separate global user-agent blocks from domain-specific path rules
* Changed `Shopware\Storefront\Page\Robots\Struct\DomainRuleStruct` to support new parser while maintaining backward compatibility
* Deprecated passing a string as the first parameter to `Shopware\Storefront\Page\Robots\Struct\DomainRuleStruct::__construct()`. Pass a `ParsedRobots` object instead
* Added new system configuration option `core.basicInformation.robotsDisableDefaults` to disable Shopware's default robots.txt rules

___
# Storefront
* Changed rendering of robots.txt to support custom User-agent blocks
* Changed robots.txt template to respect new `robotsDisableDefaults` configuration

___
# Upgrade Information

## robots.txt Configuration Enhancement

The robots.txt system has been enhanced to support the full robots.txt standard including User-agent blocks and all common directives.

### Technical Changes (Backward Compatible)

The constructor signature of `Shopware\Storefront\Page\Robots\Struct\DomainRuleStruct` has been updated to accept both `string` and `ParsedRobots` objects:

```php
// Old signature (still works)
new DomainRuleStruct('Disallow: /admin/', '/en');

// New signature (also works)
new DomainRuleStruct($parsedRobots, '/en');
```

**This change is backward compatible** - all existing code passing a string continues to work without modification. The union type `ParsedRobots|string` accepts everything the old `string` type accepted, plus the new parsed object type for internal use.

### Full robots.txt directive support

The robots.txt configuration now supports the full robots.txt standard including:

#### User-agent blocks
You can now define custom User-agent blocks with specific directives:

```
User-agent: Googlebot
Crawl-delay: 10
Disallow: /admin/

User-agent: Bingbot
Disallow: /secret/
```

#### Global vs Domain-specific rules

- **User-agent blocks** are global: They are collected from all sales channels, deduplicated, and rendered once
- **Path directives** (Allow/Disallow) within user-agent blocks are domain-specific: They get the domain's base path applied automatically

#### Example: Multi-language shop

If you have a shop with English and German sales channels at `/en` and `/de`:

**English sales channel config:**
```
User-agent: Googlebot
Crawl-delay: 10
Disallow: /account/
```

**German sales channel config:**
```
User-agent: Googlebot
Crawl-delay: 10
Disallow: /konto/
```

**Resulting robots.txt:**
```
User-agent: *
Allow: /
Disallow: /*?
Allow: /*theme/
Allow: /media/*?ts=

User-agent: Googlebot
Crawl-delay: 10
Disallow: /en/account/
Disallow: /de/konto/

Sitemap: https://example.com/en/sitemap.xml
Sitemap: https://example.com/de/sitemap.xml
```

Note how:
- The `Googlebot` block appears only once (deduplicated)
- The `Crawl-delay` directive is global (no path prefix)
- The `Disallow` directives are domain-specific (paths prefixed with `/en/` and `/de/`)

#### Supported directives

The following robots.txt directives are now supported:
- `User-agent`: Define which crawler the rules apply to
- `Allow`: Allow crawling of specific paths
- `Disallow`: Disallow crawling of specific paths
- `Crawl-delay`: Request crawl rate limit
- `Sitemap`: Specify sitemap URL
- `Request-rate`: Alternative rate limiting
- `Visit-time`: Specify crawl time windows
- `Host`: Specify preferred domain

#### Disable default rules

You can now disable Shopware's default robots.txt rules via the new configuration option "Disable default robots.txt rules" in Settings > Basic information. This gives you full control over the robots.txt content.

#### Backward compatibility

Existing configurations without User-agent directives continue to work unchanged. They will be rendered under Shopware's default `User-agent: *` block.

Before (still works):
```
Disallow: /admin/
Allow: /widgets/
```

Result: These directives are added to the default User-agent block with the domain path applied.


---
title: Support User-agent and other directives in robots.txt configuration
issue: 12787
author: Claude
author_email: noreply@anthropic.com
author_github: @anthropics
---
# Storefront
* Added support for all standard robots.txt directives (User-agent, Crawl-delay, Sitemap) in `Shopware\Storefront\Page\Robots\Struct\DomainRuleStruct`
* Added `getUserAgentBlocks()` method to `DomainRuleStruct` for properly structured robots.txt with user-agent grouping
* Added `getMergedUserAgentBlocks()` method to `RobotsPage` for merging user-agent blocks across all domains
* Added `getPathRules()` method to `DomainRuleStruct` for domain-specific path rules (backward compatibility)
* Added `getGlobalRules()` method to `DomainRuleStruct` for global directives (backward compatibility)
* Added validation for robots.txt directive types - invalid directives are now silently ignored
* Changed robots.txt template blocks to use merged user-agent blocks for proper specification compliance
* Updated Twig blocks `robots_txt_content_domain_rules_container`, `robots_txt_content_domain_rules`, and `robots_txt_content_domain_rules_rule` to output merged user-agent blocks while maintaining backward compatibility
* Fixed empty `Disallow:` and `Allow:` directives being incorrectly normalized to `/`
* Fixed User-agent directives being duplicated per domain - now output once with all domain path rules merged
* Implemented proper robots.txt block structure where directives are grouped by User-agent according to specification
* Implemented smart deduplication of non-path directives (Crawl-delay, empty Disallow/Allow) across domains while preserving all path-based rules
___
# Upgrade Information

## robots.txt Configuration Now Supports All Standard Directives

Previously, the configurable robots.txt feature only parsed `Allow` and `Disallow` directives, ignoring other valid directives like `User-agent` and `Crawl-delay`.

Now you can use all standard robots.txt directives in your configuration:

```
User-agent: Googlebot
Disallow:

User-agent: Googlebot-image
Disallow:

Disallow: /account/
Disallow: /checkout/
Allow: /widgets/cms/

User-agent: Bingbot
Crawl-delay: 10
Allow: /

Sitemap: https://example.com/sitemap.xml
```

### Block-Based Structure

Robots.txt now uses a **proper block-based structure** where directives are grouped by User-agent:

```
User-agent: *
Disallow: /account/
Allow: /public/

User-agent: Googlebot
Disallow:

User-agent: Bingbot
Crawl-delay: 10
Allow: /
```

Each `User-agent:` directive **starts a new block**, and all following directives (Disallow, Allow, Crawl-delay) belong to that user-agent until the next User-agent directive appears.

### Behavior Changes

- **Block structure**: Directives are now properly grouped by User-agent instead of separating global and path rules
- **Path-based directives** (`Allow` and `Disallow` with paths) continue to work with domain base path prefixing for multi-domain setups
- **Empty directives** (`Disallow:` or `Allow:`) are now preserved as-is without normalization - these define user-agent behavior
- **Invalid directives** are silently ignored during parsing
- **Empty values** for non-path directives (e.g., `User-agent:`) are ignored

### API Changes

If you were using `DomainRuleStruct`, a new method is available for proper robots.txt rendering:

- `getUserAgentBlocks()`: Returns an array of user-agent blocks, each containing a user-agent and its directives

Legacy methods are still available for backward compatibility:
- `getPathRules()`: Returns only domain-specific path rules (non-empty Allow/Disallow)
- `getGlobalRules()`: Returns global directives (User-agent, Crawl-delay, Sitemap) and empty path rules

**Note**: For proper robots.txt structure, use `getUserAgentBlocks()` which maintains the correct grouping of directives under their respective user-agents.

### Template Changes

The Twig blocks in `robots.txt.twig` have been updated to output the new block-based structure:

- `robots_txt_content_domain_rules_container` now iterates over `page.mergedUserAgentBlocks` instead of `page.domainRules`
- `robots_txt_content_domain_rules` now represents a user-agent block instead of per-domain rules
- `robots_txt_content_domain_rules_rule` continues to output individual rules

**Backward Compatibility**: If you have overridden these blocks in your theme, they will continue to work with the new merged user-agent block structure. The iteration variable is now a block object with `userAgent` and `rules` properties instead of a domain rule object.

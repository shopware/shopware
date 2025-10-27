---
title: Support all robots.txt directives including User-agent blocks
issue: NEXT-12787
author: Björn Meyer
author_email: b.meyer@shopware.com
author_github: BrocksiNet
---
# Core
* Added `Shopware\Storefront\Page\Robots\Parser\RobotsDirectiveParser` to parse robots.txt with error tracking and extensibility events
* Added `Shopware\Storefront\Page\Robots\Event\RobotsDirectiveParsingEvent` to allow modification of parsed results
* Added `Shopware\Storefront\Page\Robots\Event\RobotsUnknownDirectiveEvent` to handle custom directives
* Added robots.txt parsing value objects: `ParsedRobots`, `ParseIssue`, `ParseIssueSeverity`, `RobotsDirective`, `RobotsDirectiveType`, `RobotsUserAgentBlock`
* Added `Shopware\Storefront\Page\Robots\RobotsConfigChangeSubscriber` to log parsing issues when robots.txt is saved
* Added system configuration option `core.basicInformation.robotsDisableDefaults` to disable default robots.txt rules

___
# Storefront
* Changed rendering of robots.txt to support custom User-agent blocks
* Changed robots.txt template to respect new `robotsDisableDefaults` configuration

___
# Upgrade Information

## robots.txt Configuration Enhancement

The robots.txt system has been enhanced to support the full robots.txt standard including User-agent blocks and all common directives.

### Technical Changes (Backward Compatible)

The constructor of `Shopware\Storefront\Page\Robots\Struct\DomainRuleStruct` now supports both `string` and `ParsedRobots` objects as input:

```php
// Simple string format (for basic Allow/Disallow rules)
new DomainRuleStruct('Disallow: /admin/', '/en');

// ParsedRobots object format (for advanced features like User-agent blocks)
$parser = new RobotsDirectiveParser($this->eventDispatcher);
$parsedRobots = $parser->parse("
    User-agent: Googlebot
    Crawl-delay: 10
    Disallow: /admin/
", $context);
new DomainRuleStruct($parsedRobots, '/en');
```

**Both formats are fully supported** - choose the one that fits your needs. The string format is convenient for simple rules, while the `ParsedRobots` object enables advanced features like custom User-agent blocks.

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

#### Parsing error detection and logging

When you save robots.txt configuration in the admin, Shopware now validates the syntax and logs any issues:

**Error types:**
- **Errors**: Critical issues like malformed lines (missing colon separator)
- **Warnings**: Non-critical issues like unknown directives or directives outside user-agent blocks

**Logging behavior:**
- Issues are logged only when configuration is saved via admin (not on every page load)
- Each issue includes line number, line content, and error reason
- Errors are logged with `error` level, warnings with `warning` level
- Scope information indicates if the issue is in global or sales channel-specific configuration

**Example log entry:**
```
[2025-10-27T12:56:05.364906+00:00] app.WARNING: Robots.txt parsing issue at line 7: Directive 'Crawl-delay' found outside user-agent block and will be ignored {"scope":"Global","lineNumber":7,"lineContent":"Crawl-delay: 44","severity":"warning"}
```

This helps developers quickly identify and fix robots.txt configuration issues.

#### Extensibility for custom directives

The robots.txt parser now dispatches events that allow developers to extend the parsing behavior:

**`RobotsDirectiveParsingEvent`** - Dispatched after parsing completes:
- Modify the parsed result (add/remove user-agent blocks or directives)
- Add custom validation rules and issues
- Transform directives based on custom business logic

**`RobotsUnknownDirectiveEvent`** - Dispatched when an unknown directive is encountered:
- Handle custom directives not in the standard set (e.g., Yandex's `Clean-param`)
- Prevent warnings for known-custom directives
- Add custom error messages for specific directive types

**Example**: Supporting Yandex's `Clean-param` directive:
```php
#[AsEventListener(event: RobotsUnknownDirectiveEvent::class)]
class YandexDirectiveSubscriber
{
    public function onUnknownDirective(RobotsUnknownDirectiveEvent $event): void
    {
        if ($event->getDirectiveType() === 'Clean-param') {
            // Mark as handled so it doesn't generate a warning
            $event->setHandled(true);
        }
    }
}
```

**Example**: Adding custom validation to enforce security policies:
```php
#[AsEventListener(event: RobotsDirectiveParsingEvent::class)]
class RobotsSecurityPolicySubscriber
{
    public function onParsingComplete(RobotsDirectiveParsingEvent $event): void
    {
        $parsed = $event->getParsedResult();

        // Ensure /admin/ is always disallowed for all bots
        $hasAdminDisallow = false;
        foreach ($parsed->orphanedPathDirectives as $directive) {
            if ($directive->type === RobotsDirectiveType::DISALLOW && $directive->value === '/admin/') {
                $hasAdminDisallow = true;
                break;
            }
        }

        if (!$hasAdminDisallow) {
            // Add a warning about missing security directive
            $newIssues = [
                ...$parsed->issues,
                new ParseIssue(0, '', 'Security: /admin/ should be disallowed', ParseIssueSeverity::WARNING),
            ];
            $event->setParsedResult(new ParsedRobots(
                $parsed->userAgentBlocks,
                $parsed->orphanedPathDirectives,
                $newIssues
            ));
        }
    }
}
```

This event-driven approach allows developers to extend the robots.txt functionality without modifying core code.

#### Backward compatibility

Existing configurations without User-agent directives continue to work unchanged. They will be rendered under Shopware's default `User-agent: *` block.

Before (still works):
```
Disallow: /admin/
Allow: /widgets/
```

Result: These directives are added to the default User-agent block with the domain path applied.

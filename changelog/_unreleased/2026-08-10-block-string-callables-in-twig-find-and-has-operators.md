---
title: Block string callables in Twig find filter and has some/has every operators
issue:
---
# Core
* Changed the Twig `find` filter and the `has some` / `has every` operators to reject string callables unless the function is listed in `shopware.twig.allowed_php_functions`, matching the existing behaviour of the `map`, `filter`, `reduce`, and `sort` filters. This closes a sandbox escape (`GHSA-6qhw-38wm-7g7h`) where a locally installed App could execute arbitrary PHP and OS commands through App Scripts. The policy applies to every Shopware Twig environment (storefront and theme templates, SEO URL templates, App Scripts). Templates passing arrow functions (`v => ...`) are unaffected; allow-listed string functions are called with the value as their only argument. The guard is applied when a template is compiled, so it only takes effect once the Twig cache is rebuilt (`bin/console cache:clear`, part of the standard update process).

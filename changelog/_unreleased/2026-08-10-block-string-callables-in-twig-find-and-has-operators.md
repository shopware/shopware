---
title: Twig templates can no longer call arbitrary PHP functions through `find`, `has some`, and `has every`
issue: #304
---
# Core
* Changed the Twig `find` filter and the `has some` / `has every` operators to reject string callables that are not listed in `shopware.twig.allowed_php_functions`, matching the existing behaviour of the `map`, `filter`, `reduce`, and `sort` filters. Templates passing arrow functions (`v => ...`) are unaffected; add any string callable a template legitimately needs to the allowlist.

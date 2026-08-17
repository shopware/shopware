---
title: Block string callables in Twig find filter and has some/has every operators
issue:
---
# Core
* Changed the Twig `find` filter and the `has some` / `has every` operators to reject string callables that are not listed in `shopware.twig.allowed_php_functions`, matching the existing behaviour of the `map`, `filter`, `reduce`, and `sort` filters. Templates passing arrow functions (`v => ...`) are unaffected; add any string callable a template legitimately needs to the allowlist.

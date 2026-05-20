---
title: Register Doctrine enum type mapping
issue: #9651
---
# Core
* Added a central Doctrine type mapping for MySQL `enum` columns so DAL validation can inspect enum-backed tables instead of skipping them.

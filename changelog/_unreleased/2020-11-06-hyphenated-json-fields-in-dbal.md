---
title: Hyphenated JSON fields in DBAL
issue: 
author: Uwe Kleinmann
author_email: u.kleinmann@kellerkinder.de
author_github: @kleinmann
---
# Core
* Changed JSON accessor builder to quote field names. This allows for hyphenated fields (like `my-hyphenated-field`) in queries using JSON fields, where previously an "Invalid JSON path expression" error was thrown by MySQL.

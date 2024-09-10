---
title: phpstan rule to validate acl names
issue: NEXT-25614
---
# Core
* Added phpstan rules to validate acl names.
* Changed `DomainExceptionRule` to skip phpstan rules directory.
* Changed pipelines configuration to generate entity schema before running phpstan, so the new rules can access entities names.

---
title: Fix document is deleted after deleting document logo
issue: NEXT-7394
---
# Core
*  Changed foreign key `document_base_config`'s `fk.document_base_config.logo_id` constraint from `DELETE CASCADE` to `DELETE SET NULL`.

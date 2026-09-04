---
title: Block admin escalation through profile avatar media
issue:
author: Umut Dogan
author_email: u.dogan@shopware.com
---
# API
* Changed `PATCH /api/_info/me` to reject an `avatarMedia` payload that contains associations of the media entity, such as `user` or `avatarUsers`, with a `403` and the error code `FRAMEWORK__MISSING_PRIVILEGE_ERROR`.

---
title: Block admin escalation through profile avatar media
issue:
author: Umut Dogan
author_email: u.dogan@shopware.com
---
# API
* Changed `PATCH /api/_info/me` to accept `avatarMedia` only in the form `{"id": "<media-id>"}`. Every other payload now gets a `403` with the error code `FRAMEWORK__MISSING_PRIVILEGE_ERROR`.

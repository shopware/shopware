---
title: Block admin escalation through profile avatar media
issue:
author: Umut Dogan
author_email: u.dogan@shopware.com
---
# API
* Changed `PATCH /api/_info/me` to accept `avatarMedia` only in the form `{"id": "<media-id>"}`. Every other payload now gets a `403` with the error code `FRAMEWORK__MISSING_PRIVILEGE_ERROR`. This includes a nested `user` or `avatarUsers` association, a field of the media entity such as `private`, and `null`. To set the avatar by id, send `avatarId`.

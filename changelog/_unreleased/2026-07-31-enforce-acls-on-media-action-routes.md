---
title: Enforce ACLs on media action routes
issue: #18770
---
# API
* Changed the media action routes to require ACL privileges, as their DAL writes run in system scope and bypassed the entity-level ACL check: `POST /api/_action/media/{mediaId}/upload` and `POST /api/_action/media/{mediaId}/rename` require `media:update`, and `GET /api/_action/media/provide-name` requires `media:read`. Admin API integrations and users calling these routes must be granted the respective privilege.

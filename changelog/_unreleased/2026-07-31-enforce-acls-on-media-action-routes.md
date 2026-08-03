---
title: Media action routes now enforce ACL privileges
issue: #18770
---
# API
* Changed the Admin API media action routes to enforce their corresponding ACL privileges. Clients must have `media:update` to upload content to existing media or rename media, and `media:read` to use the media filename lookup route. The filename lookup route already required `media:read` through its repository query. The legacy upload and rename routes now enforce permissions that their system-scoped DAL writes did not previously require. Integrations and users that call those routes must update their ACL role.

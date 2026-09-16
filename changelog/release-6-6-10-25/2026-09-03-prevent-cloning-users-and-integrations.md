---
title: Prevent cloning users and integrations
issue: #399
---
# API
* Changed `POST /api/_action/clone/user/{id}` and `POST /api/_action/clone/integration/{id}` to return `403` instead of creating a clone. User and integration records can no longer be cloned through the Admin API.

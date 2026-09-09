---
title: User validation endpoints now require ACL privileges
issue:
---
# API
* Changed the user email and username uniqueness validation endpoints to require the `user:read` privilege. Requests with tokens lacking this privilege now receive a `403` response.

---
title: Restrict user, access key and admin integration writes
issue:
---
# API
* Changed user and user access key writes through the Admin API: a non-admin may only update or delete their own user and their own access keys. Other writes now get a `403` with the error code `USER__USER_CHANGE_NOT_ALLOWED`.
* Changed integration writes through the Admin API: a non-admin may no longer update or delete an admin integration. These writes now get a `403` with the error code `INTEGRATION__ADMIN_INTEGRATION_CHANGE_NOT_ALLOWED`.
* Changed user access keys: the user of an access key can no longer be changed after the key was created, also not by administrators. Such writes now get a `400` with the error code `FRAMEWORK__WRITE_CONSTRAINT_VIOLATION`.

---
title: ACL roles and protected Administration fields use authorized write paths
issue: #304
---
# Core
* Changed generic Admin API writes so they can no longer create or update `acl_role` entities. Direct DAL writes to the `admin` fields of users and integrations remain system-only; the authenticated Administration API controllers continue to authorize these changes, while self-profile and integration management work as before.

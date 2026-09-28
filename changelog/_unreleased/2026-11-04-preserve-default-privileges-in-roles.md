---
title: Preserve default privileges in saved roles
issue: 20845
---

# Administration

* Changed the role editor to preserve explicitly stored default user privileges and allow selecting or deselecting them in detailed permissions.
* Changed new roles to preselect default user privileges so they are also available to integrations assigned to the role.

# Core

* Added a migration restoring `language:read`, `locale:read`, `log_entry:create`, and `message_queue_stats:read` for existing integration roles, excluding app roles and deleted roles.

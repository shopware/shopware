---
title: Administration users receive default runtime privileges
issue: #17048
---
# Core
* Added `Shopware\Core\Framework\Api\Context\AdminApiSource::DEFAULT_USER_PRIVILEGES`. Authenticated Administration users now receive the default privileges required by global Admin helpers at runtime: `language:read`, `locale:read`, `message_queue_stats:read`, `log_entry:create`, `currency:read`, and `country:read`. `currency:read` and `country:read` were not granted implicitly before, so users whose ACL roles do not list them gain read access to currencies and countries. The defaults apply to Administration users only; integrations still need the privileges in their ACL role.
___
# Administration
* Changed the role editor to no longer write the default privileges into a role's stored privilege set, as the server grants them at runtime. Existing roles keep the entries they already contain, and the detailed permissions grid shows the defaults as granted.

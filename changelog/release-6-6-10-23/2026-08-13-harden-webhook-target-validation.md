---
title: Harden webhook target validation
issue: #18937
---
# Core
* Added App System and webhook target validation to prevent requests to private, reserved, or otherwise disallowed network addresses. Requests must use HTTPS by default; operators can configure `shopware.app_system.allow_unencrypted_traffic` or explicitly allow private IP addresses through `shopware.app_system.allowed_private_ip_addresses` when required.

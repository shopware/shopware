---
title: Harden webhook target validation
issue: #18937
---
# Core
* Added webhook target validation to prevent webhook delivery to private, reserved, or otherwise disallowed network addresses. Webhook targets must use HTTPS by default; operators can configure `shopware.webhook.allow_unencrypted_traffic` or explicitly allow IP addresses through `shopware.webhook.allowed_ip_addresses` when required.

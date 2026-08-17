---
title: Webhook target validation hardened
issue: #304
---
# Core
* Changed webhook delivery to validate outbound targets before every request and before every followed redirect. By default, webhook targets must use HTTPS and resolve only to public IP addresses. HTTP endpoints, IP-literal targets, and internal network targets are rejected unless the operator explicitly allows the required traffic through `shopware.app_system.allow_unencrypted_traffic` or `shopware.app_system.allowed_private_ip_addresses` in `shopware.yaml`.
* Changed webhook delivery to pin the DNS result used during validation to the actual HTTP request, reducing DNS rebinding risk between validation and connection.

---
title: Disable app system target validation in dev
issue: 19835
---
# Core
* Added the configuration option `shopware.app_system.enable_url_validation`. It defaults to `true`. When it is `false`, app system requests and webhooks skip target validation: the HTTPS requirement, the private network checks and the DNS pinning.
* Changed the `dev` environment to ship `shopware.app_system.enable_url_validation: false` instead of `allow_unencrypted_traffic: true` and an `allowed_private_ip_addresses` list. Local app servers and webhook endpoints now work over HTTP and on private or Docker-internal hosts without further configuration.
* Changed `Shopware\Core\Framework\Webhook\Validation\WebhookTarget::$ip` to be nullable. It is `null` when target validation is disabled, and `WebhookEventMessageHandler` then does not pin the connection.
___
# Upgrade Information
## App system target validation can be disabled for local development
While `shopware.app_system.enable_url_validation` is `false`, the options `shopware.app_system.allow_unencrypted_traffic` and `shopware.app_system.allowed_private_ip_addresses` have no effect. Keep the validation enabled in production.

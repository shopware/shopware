---
title: Allow HMAC JWT
issue: NEXT-33691
---

# Core

* Added new parameter `shopware.api.jwt_key.use_app_secret` to use the `APP_SECRET` as HMAC for the JWT tokens.

___

# Upgrade Information
## HMAC JWT keys

Usage of normal RSA JWT keys is deprecated. And will be removed with Shopware 6.7.0.0. Please use the new HMAC JWT keys instead using configuration:

```yaml
shopware:
    api:
        jwt_key:
              use_app_secret: true
```

Also make sure that the `APP_SECRET` environment variable is at least 32 characters long. You can use the `bin/console system:generate-app-secret` command to generate an valid secret.

Changing this will invalidate all existing tokens and require a re-login for all users and all integrations.


---
title: Remove experimental admin SSO
author: Soner Sayakci
author_email: s.sayakci@gmail.com
author_github: @shyim
---
# Core
* Removed the experimental SSO implementation `Shopware\Core\Framework\Sso` including all services, grant types (`ShopwareGrantType`, `ShopwarePasswordGrantType`, `ShopwareRefreshTokenGrantType`) and the `shopware.admin_login` configuration section. The feature was `@internal` and experimental.
* Removed the API routes `/api/oauth/sso/config`, `/api/oauth/sso/auth`, `/api/oauth/sso/code`, `/api/_action/sso/invite-user` and `/api/_info/is-sso`.
* Changed `Shopware\Core\Framework\Api\EventListener\Authentication\ApiAuthenticationListener` to register the plain `league/oauth2-server` password and refresh token grants again.
* Changed `Shopware\Core\Framework\Api\Controller\UserController` to revoke refresh tokens directly via `Shopware\Core\Framework\Api\OAuth\RefreshTokenRepository` on logout.
* Added migration `Shopware\Core\Migration\V6_8\Migration1782975429DropSsoOauthUserTable` that drops the unused `oauth_user` table in the destructive migration phase.
___
# Administration
* Removed the `sw-sso-error` module, the `sw-sso-users-permission-user-detail` page and the components `sw-user-sso-invitation-modal`, `sw-user-sso-status-label` and `sw-user-sso-access-key-create-modal`.
* Removed the services `ssoSettingsService` and `ssoInvitationService`.
* Removed `logoutSso()` and `getLoginTemplateConfig()` from the login service.
* Removed the route `sw.users.permissions.user.sso.detail`.
___
# Upgrade Information

## Removal of the experimental admin SSO

The experimental single sign-on login for the Administration has been removed without a deprecation period, as permitted for experimental features. This affects you only if you configured the `shopware.admin_login` section in your `shopware.yaml`.

A replacement providing OIDC single sign-on, passkeys and multi-factor authentication is in development under the `ADMIN_AUTH` feature flag. Until you migrate, remove the `shopware.admin_login` configuration; admin users log in with username and password again. Users that were provisioned through SSO keep working, as they are regular admin users.

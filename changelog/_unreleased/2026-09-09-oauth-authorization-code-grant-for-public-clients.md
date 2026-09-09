---
title: OAuth authorization code grant with PKCE for public clients
author: Soner Sayakci
author_email: s.sayakci@shopware.com
author_github: @shyim
---
# Core
* Added `Shopware\Core\Framework\Api\OAuth\Client\PublicClientRegistry`, holding the public OAuth clients configured under `shopware.api.oauth_clients`. Shopware ships the `shopware-cli` client with loopback redirect URIs by default.
* Added the `authorization_code` grant (`Shopware\Core\Framework\Api\OAuth\ShopwareAuthCodeGrantType`) to the Admin API authorization server. PKCE with the `S256` method is mandatory, public clients are limited to the `authorization_code` and `refresh_token` grants.
* Added `Shopware\Core\Framework\Api\OAuth\AuthCodeRepository` and the `oauth_auth_code` table to make issued authorization codes single use. Codes expire after `shopware.api.auth_code_ttl` (default `PT5M`).
* Added `Shopware\Core\Framework\Api\OAuth\GrantTypeFactory`, which builds the grant types enabled by `Shopware\Core\Framework\Api\EventListener\Authentication\ApiAuthenticationListener`.
* Added optional constructor parameters `$redirectUris` and `$grantTypes` and the method `supportsGrantType()` to `Shopware\Core\Framework\Api\OAuth\Client\ApiClient`. `getRedirectUri()` now returns an empty array instead of failing on an uninitialized property.
* Changed `Shopware\Core\Framework\Api\OAuth\ClientRepository` to resolve public clients from the registry.
* Changed `Shopware\Core\Framework\Api\OAuth\ScopeRepository` to grant the `write` scope for the `authorization_code` grant.
___
# API
* Added `GET /api/oauth/authorize`, the authorization endpoint of the OAuth authorization code flow. It validates the request and forwards the browser to the consent page of the Administration.
* Added `GET /api/oauth/authorize/info` and `POST /api/oauth/authorize`, used by the consent page to display and complete the authorization request. Both require a user-bound access token.
* Added the `authorization_code` grant type to `POST /api/oauth/token`. The resulting tokens act with the permissions of the user who approved the request.
* Added the `authorizationCode` flow to the OpenAPI security scheme of the Admin API.
___
# Administration
* Added the `sw-oauth-authorize` module with the standalone consent page under `#/oauth/authorize`.
* Added `oauthAuthorizeApiService` to call the authorization endpoints.
___
# Upgrade Information
## Registering additional public OAuth clients
Public clients such as CLI tools or native apps cannot keep a client secret. Register them under `shopware.api.oauth_clients` to let them obtain user-bound tokens through the authorization code flow with PKCE:
```yaml
shopware:
    api:
        oauth_clients:
            my-tool:
                name: 'My Tool'
                redirect_uris:
                    - 'http://127.0.0.1/callback'
```
Redirect URIs are matched exactly, except for loopback URIs (`http://127.0.0.1`, `http://[::1]`), where any port is accepted.

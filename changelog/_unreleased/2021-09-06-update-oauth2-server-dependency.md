---
title: Update league/oauth2-server dependency
issue: NEXT-16841
---
# Core 
* Changed version of `league/oauth2-server` to at least 8.3.2.
* Changed `\Shopware\Core\Framework\Api\OAuth\ClientRepository::getClientEntity()` to return null, which will result in the oAuth library raising an `OAuthServerException::invalidClient()` exception, instead of raising an `OAuthServerException::invalidCredentials()` manually.

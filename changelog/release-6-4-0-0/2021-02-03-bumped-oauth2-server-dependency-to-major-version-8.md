---
title: Bumped oauth2-server dependency to major version 8
issue: NEXT-7873
author: Lennart Tinkloh
author_email: l.tinkloh@shopware.com 
author_github: lernhart
---
# Core
* Added `src/Core/Framework/Api/OAuth/ClientRepository::validateClient`
* Added `src/Core/Framework/Api/OAuth/Client/ApiClient::isConfidential`
* Added `src/Core/Checkout/Payment/Cart/Token/JWTConfigurationFactory`, which creates an injectable JWT configuration object
* Added parameter `configuration` to `src/Core/Framework/Api/OAuth/BearerTokenValidator` constructor.

___
# Upgrade Information
The parameter signature of `src/Core/Framework/Api/OAuth/ClientRepository::getClientEntity` changed due to the major update of the oauth2-server dependency.
OAuth2-Clients should be validated separately in the new `validateClient` method.
See: https://github.com/thephpleague/oauth2-server/pull/938

The parameter signature of `src/Core/Checkout/Payment/Cart/Token/JWTFactoryV2` changed.
It uses the injected configuration object rather than a private key.

The parameter signature of `src/Core/Framework/Api/OAuth/BearerTokenValidator` changed.
The injected configuration object was added as parameter.

---
title: Communication from storefront to app server
issue: NEXT-29321
---

# Core

* Added new store-api route `POST /store-api/app-system/{name}/generate-token` to generate a JWT to identify the client on the app server.

___

# Storefront

* Added new `src/service/app-client.service` to communicate with the app server with context information in a JWT token.

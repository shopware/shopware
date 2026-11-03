---
title: Restrict HTTP method overrides to form actions
issue: 22120
---

# API

* Changed POST method overrides through the `X-HTTP-Method-Override` header or `_method` parameter to accept only PUT, PATCH and DELETE.
  Send GET and HEAD requests with their actual HTTP method so routing and HTTP caches agree on the request semantics.
  HTML forms can continue to use POST with an override for PUT, PATCH and DELETE actions.

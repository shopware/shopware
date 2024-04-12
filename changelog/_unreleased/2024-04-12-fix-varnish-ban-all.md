---
title: Fix varnish ban all
issue: NEXT-31926
---

# Core

* Changed `VarnishReverseProxyGateway::banAll` to use `BAN` http method instead of `PURGE` to clear site-wide cache.

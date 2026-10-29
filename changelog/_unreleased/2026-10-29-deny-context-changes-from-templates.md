---
title: Harden the context handling of templates and app scripts
issue:
---
# Core
* Changed Twig templates to only read the request context. A method call that would change it now fails with the error code `FRAMEWORK__TWIG_METHOD_CALL_NOT_ALLOWED`.
* Changed the `repository` and `writer` app script services to use their own context for every call.

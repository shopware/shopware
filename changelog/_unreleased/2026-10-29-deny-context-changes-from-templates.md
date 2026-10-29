---
title: Deny context changes from templates
issue:
---
# Core
* Changed Twig templates: they can no longer call methods that change the request context or the request, such as setters. Reading them stays possible, for example `context.languageId`. Such a call now fails with the error code `FRAMEWORK__TWIG_METHOD_CALL_NOT_ALLOWED`; make the change in PHP instead, for example in an event subscriber.
* Changed the `repository` and `writer` app script services: every call runs with its own app context, so a context changed by a script is not reused by later calls.

---
title: Let Twig templates add extensions to the request context
issue:
---
# Core
* Changed `SwTwigFunction` to allow `addExtension()`, `addArrayExtension()` and `addExtensions()` on the request context again. Removing or replacing extensions stays denied.

---
title: Change URL generation for ESI includes to avoid HTTPS issues with Varnish
author: Stefan Poensgen
author_email: mail@stefanpoensgen.de
author_github: @stefanpoensgen
---
# Storefront
* Changed generation of ESI URL in `src/Storefront/Resources/views/storefront/base.html.twig` from `url()` to `path()`.
  This ensures that only the path is used for ESI fragments, so Varnish is not forced to handle HTTPS internally.
  This change allows ESI fragments to be rendered correctly when the site is accessed via HTTPS, as Varnish cannot process ESI with HTTPS URLs.

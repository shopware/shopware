---
title: Restore homepage hreflang links for three-argument HreflangLoaderParameter callers
author: Matthias Breddin
author_email: mb@lunetics.com
author_github: @lunetics
---
# Core
* Added a deprecated route-name fallback to `HreflangLoaderParameter::isHomepage()`, so callers that construct the parameter without the `$homepage` flag keep receiving homepage hreflang links. Since the flag was introduced, `HreflangLoader` reads it instead of comparing the route name, and its `false` default silently disabled homepage handling for callers outside the repository. The fallback emits a deprecation and is removed in v6.8.0.0.

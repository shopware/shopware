---
title: Fix Symfony bundle container access
issue: NEXT-29615
---
# Core
* Changed all accesses to `$this->container` from within symfony bundles to assert that the container was set before, see [Symfony PR](https://github.com/symfony/symfony/pull/50615) for more information.
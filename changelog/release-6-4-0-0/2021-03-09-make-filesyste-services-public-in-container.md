---
title: Make filesystem services public in dependency injection container
issue: NEXT-14343
author: Manuel Kress
author_email: 6232639+windaishi@users.noreply.github.com
author_github: windaishi
---
# Core
* Changed all file system services (e.g. `shopware.filesystem.private`) to now be public in the dependency injection container.
  * This makes them directly available via `$container->get()` without the necessity to inject them as service.

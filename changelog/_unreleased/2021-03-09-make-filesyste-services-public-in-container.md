---
title: Make filesystem services public in dependency injection container
author: Manuel Kress
author_email: 6232639+windaishi@users.noreply.github.com
author_github: windaishi
---
# Core
* All file system services (e.g. `shopware.filesystem.private`) are now public in the dependency injection container.
  * This makes them directly available via `$container->get()` without the necessity to inject them as service.

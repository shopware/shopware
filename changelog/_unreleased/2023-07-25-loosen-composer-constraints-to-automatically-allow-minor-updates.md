---
title: Loosen composer constraints to automatically allow minor updates of dependencies
issue: NEXT-29168
---
# Core
* Changed all composer constraints to allow minor updates of dependencies with the [caret operator](https://getcomposer.org/doc/articles/versions.md#caret-version-range-). There are some exclusions, though:
  * Symfony dependencies: We still use the tilde operator for Symfony dependencies to allow minor updates of Symfony components. This is because we are so heavily coupled to symfony that even minor updates potentially break the system.
  * DomPDF: The lib caused PDF generation issues after patch releases multiple times in the past, therefore we still pin a specific version.
  * Phpstan and extensions: We still pin a specific version to avoid minor updates breaking our CI pipeline, by now catching more errors.

---
title: Ensure extension ZIP files do not contain path traversals
author: Michael Telgmann
author_github: @mitelg
---

# Core

* Changed `\Shopware\Core\Framework\Plugin\ExtensionExtractor` to correctly check ZIP files for path traversals.

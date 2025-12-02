---
title: Change file_exists to is_dir
issue: https://github.com/shopware/shopware/issues/5913
author: tinect
author_email: s.koenig@tinect.de
author_github: tinect
---

# Core

* Changed `file_exists` to `is_dir` in `TwigLoaderConfigCompilerPass` and `ComposerPluginLoader` to slightly improve performance when checking for the existence of a directory.

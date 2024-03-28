---
title: Improve snippet author performance
issue: NEXT-00000
author: Benjamin Wittwer
author_email: dev@a-k-f.de
author_github: akf-bw
---
# Core
* Changed the `loadPluginSnippets` method in `SnippetFileLoader` to load the plugin author list before the loop

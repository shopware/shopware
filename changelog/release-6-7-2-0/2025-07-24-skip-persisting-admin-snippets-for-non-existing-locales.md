---
title: Skip persisting admin snippets for non-existing locales
author: Frederik Schmitt
author_email: f.schmitt@shopware.com
author_github: @fschmtt
---
# Core
# Administration
* Changed `Shopware\Administration\Snippet\AppAdministrationSnippetPersister::updateSnippets()` to skip persisting snippets for locales that do not exist in the system instead of throwing an exception.

---
title: Do not alter distPath for symlinked themes
author: Christian Schiffler
author_email: c.schiffler@cyberspectrum.de
author_github: discordier
---
# Storefront
* Changed `\Shopware\Storefront\Theme\ThemeCompiler::getScriptDistFolders()` to only add the technical name of the
  plugin to the dist path if the original dist path does not exist.

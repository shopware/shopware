---
title: Fix plugin storefront js code being compiled twice
issue: NEXT-23930
author: Maximilian Rüsch
author_email: maximilian.ruesch@pickware.de
author_github: maximilianruesch
---
# Storefront
* Ensures a previously activated plugin is not added twice to the list of plugins which should be recompiled during the following theme compilation. This list may already contain the plugin.

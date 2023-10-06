---
title: Fix plugin storefront js code being compiled twice
issue: NEXT-23930
author: Maximilian Rüsch
author_email: maximilian.ruesch@pickware.de
author_github: maximilianruesch
---
# Storefront
* Changed the theme compilation process to ensure that a previously activated plugin is not added twice to in the list of plugins which should be recompiled. This list may already contain the plugin.

---
title: Fix plugin storefront js code being compiled twice
author: Maximilian Rüsch
author_email: maximilian.ruesch@pickware.de
author_github: maximilianruesch
___
# Storefront
*  Ensures a previously activated plugin is not added twice to the list of plugins which should be recompiled during the following theme compilation. This list may already contain the plugin.

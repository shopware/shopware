---
title: Added events for concatenated styles/scripts in theme compiler
issue: NEXT-14227
author: David Neustadt
author_email: kontakt@davidneustadt.de
author_github: dneustadt
---
# Storefront
* Added `Symfony\Contracts\EventDispatcher\Event\ThemeCompilerConcatenatedStylesEvent` and `Symfony\Contracts\EventDispatcher\Event\ThemeCompilerConcatenatedScriptsEvent`.
    * Pass concatenated styles and scripts through dedicated events before further compilation

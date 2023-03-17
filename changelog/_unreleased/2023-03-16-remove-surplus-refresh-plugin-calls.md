---
title: Remove surplus refresh plugin calls
author: Maximilian Rüsch
author_email: maximilian.ruesch@pickware.de
author_github: maximilianruesch
---
# Core
* Removed surplus calls to `plugin:refresh` routine as they are executed later in all relevant workflows and can lead to irrecoverable DI container crashes
___
# Administration
* Added a missing `plugin:refresh` call in the first run wizard during paypal installation.

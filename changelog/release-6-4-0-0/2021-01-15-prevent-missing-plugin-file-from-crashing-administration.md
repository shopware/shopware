---
title: Prevent error in loading plugin administration files from crashing the administration.
issue: NEXT-14918
author: Niklas Büchner
author_email: niklas.buechner@pickware.de
---
# Administration
* Changed the plugin loading process to gracefully handle a missing javascript file of a plugin instead of crashing.
* Changed method `injectPlugin` in `Resources/app/administration/src/core/application.js` and added a try/catch to prevent JavaScript errors if plugin asset files cannot be found.

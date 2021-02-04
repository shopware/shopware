---
title: Fix plugin list actions
issue: NEXT-11466
author: Jannis Leifeld
author_email: j.leifeld@shopware.com 
author_github: @jleifeld
---
# Administration
* Added method `setPlugins` for the `update-records` event in `sw-plugin-list`. This fixes the watcher which updates the action entries.

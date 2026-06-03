---
title: Fix app module iframe not updating when the location id changes
issue: NEXT-36065
author: Mark Ng
author_email: markng1993@gmail.com
author_github: socrec
---
# Administration
* Added a `locationId` watcher to `sw-iframe-renderer` so the iframe src is re-signed whenever the location id changes, not only when the extension/baseUrl changes. This fixes app modules that share one baseUrl (e.g. an app's "Overview" and "Settings" menu items) not updating their content when navigating between them. Backport of NEXT-36065.

---
title: Add extension capability to cards to the extension api
issue: NEXT-18129
author: Jannis Leifeld
author_email: j.leifeld@shopware.com
author_github: Jannis Leifeld
---
# Administration
* Added plugin for the VueDevtools (only available in version 6+) to find out the positionId's of the extension points
* Added component sections before and after a card
* Added the property `positionIdentifier` to the `sw-card` (required in next major)
* Added a componentSection component for rendering custom UIs via the ExtensionAPI
* Added a iframe-renderer component for rendering iFrames with specific locationIds

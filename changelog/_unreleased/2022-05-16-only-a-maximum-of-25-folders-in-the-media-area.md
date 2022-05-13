---
title: Only a maximum of 25 folders in the media area 
issue: NEXT-20815
author: Niklas Limberg
author_email: n.limberg@shopware.com
author_github: NiklasLimberg
---
# Administration
* Removed deprecation for `folderLoaderDone` data property in `sw-media-library/index.js`, since it was still needed.
* Changed the `showLoadMoreButton` computed in `sw-media-library/index.js` to return true if either additional media or folders can be loaded.
* Changed the `loadItems` method in `sw-media-library/index.js` to fetch media and folders simultaneously with `nextMedia` and `nextFolders` respectively.
* Added the `nextMedia` method in `sw-media-library/index.js` to allow for the aforementioned simultaneous fetching
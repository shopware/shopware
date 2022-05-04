---
title: Media library folder search does not include subfolders
issue: NEXT-20964
---
# Administration
* Changed method `nextFolders` in `src/module/sw-media/component/sw-media-library/index.js` to remove condition parent id equal with current folder.
* Changed watch `routeFolderId` in `src/module/sw-media/page/sw-media-index/index.js` to reset search term.

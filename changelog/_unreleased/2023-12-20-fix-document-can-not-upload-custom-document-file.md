---
title: Fix Document can not upload custom document file
issue: NEXT-31974
---
# Administration
* Changed the method `checkFileType` in `src/app/asyncComponent/media/sw-media-upload-v2/index.js` to change file in case it does not belong to `File`.

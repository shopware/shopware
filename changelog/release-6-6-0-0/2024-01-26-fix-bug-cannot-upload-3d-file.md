---
title: Fix bug cannot upload 3D file
issue: NEXT-33377
---
# Administration
* Changed the method `checkFileType` in `src/app/asyncComponent/media/sw-media-upload-v2/index.js` to reset file type and file name only if the file is media entity.

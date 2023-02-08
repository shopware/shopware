---
title: Respect filesystem on duplicate upload
issue: NEXT-25129
author: d.neustadt
author_email: d.neustadt@shopware.com
author_github: dneustadt
---
# Administration
* Added `isPrivate` argument to `UploadTask` and set parameter according to used filesystem on upload to check for duplicates in same filesystem

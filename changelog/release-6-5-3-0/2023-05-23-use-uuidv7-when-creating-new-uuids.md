---
title: use uuidv7 when creating new uuids
issue: NEXT-27486
author: Jochen Manz
author_email: j.manz@kellerkinder.de
author_github: jochenmanz
---
# Core
* Changed the uuid v4 generation with Ramsey/Uuid v7 implementation to use the v7 time-based UUID bit layout
```

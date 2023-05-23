---
title: use uuidv7 when creating new uuids
issue: NONE
author: Jochen Manz
author_email: j.manz@kellerkinder.de
author_github: jochenmanz
---
# Core
* Replaces the uuid v4 generation with Ramsey/Uuid v7 implementation to use the v6 time-based UUID bit layout
```

---
title: Add store API endpoint to fetch media entities
issue: NEXT-31903
author: Björn Meyer
author_email: b.meyer@shopware.com
author_github: BrocksiNet
---

# API
* Added `media` endpoint to `store-api/media` to fetch media entities by id
___
Post body example:
```json
{
    "ids" : [
        "018d8922df51736c98bec29c2d1f813f",
        "018d8923a32879cfadf561fb02c479e0"
    ]
}
```

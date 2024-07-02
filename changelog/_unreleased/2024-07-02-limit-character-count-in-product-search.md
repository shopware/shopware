---
title: Limit character count in product search
issue: NEXT-00000
author: Elias Lackner
author_email: lackner.elias@gmail.com
author_github: @lacknere
---
# Core
* Added `MAX_CHARACTER_COUNT` constant and `limitCharacterCount` method in `ProductSearchTermInterpreter` to prevent exploding keyword SQL query in case of very long search inputs.

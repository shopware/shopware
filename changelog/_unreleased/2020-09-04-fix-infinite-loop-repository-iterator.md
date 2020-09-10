---
title:          Fixed an infinite loop when using the repository iterator
issue:          NEXT-10292
author:         Joshua Behrens
author_email:   behrens@heptacom.de
author_github:  @JoshuaBehrens
---
# Core
* Added default limit to criteria in the `RepositoryIterator` to prevent an infinite loop

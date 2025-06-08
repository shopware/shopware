---
title: Fix extension column export
issue: NEXT-14993
author: Malte Janz
author_email: m.janz@shopware.com 
author_github: Malte Janz
---
# Core
* Changed `EntitySerializer` to also serialize entity extension fields
* Changed `CriteriaBuilder` to also include extensions as associations if they are used in the mapping
* Changed `KeyMappingPipe` to point to the right path if the field is part of an entity extension

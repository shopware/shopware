---
title: Fixed entity exists and not validators work correctly with composite constraints.
issue: NEXT-16566
author: Andreas Allacher
author_email: andreas.allacher@massiveart.com
author_github: @AndreasA
---
# Core
* Changed `EntityExistsValidator` and `EntityNotExistsValidator` to work in combination with composite constraints like Symfony's `All` constraint by cloning the criteria object.
* Changed `EntityNotExistsValidator` to only be valid, if the expected entity does not exist.
* Changed `EntityExistsValidator` and `EntityNotExistsValidator` to only include one entity in the result as that is enough to determine exists for non `id` property searches.

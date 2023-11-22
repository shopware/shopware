---
title: Use property name in price serialization
author: Joshua Behrens
issue: NEXT-31590
author_email: code@joshua-behrens.de
author_github: @JoshuaBehrens
---
# Core
* Changed `PriceFieldSerializer` to fix the validation path to include the property name of the field, that is validated instead of static `price` as name

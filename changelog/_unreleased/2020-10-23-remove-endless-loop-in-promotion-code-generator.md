---
title: Remove endless loop in promotion code generator
author: Joshua Behrens
author_email: code@joshua-behrens.de
author_github: @JoshuaBehrens
---
# Administration
* Added check in loop in `individual-code-generator.service.js` to prevent further iterations when no further codes are possible by pattern definition

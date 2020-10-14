---
title: Deprecate empty id arrays in Criteria constructors
author: Hendrik Söbbing
author_email: hendrik@soebbing.de 
author_github: @soebbing
---
# Core
* Deprecated the support for empty id arrays in `Critera` constructors due to inconsistencies. Use `null` instead or
just no parameter at all.

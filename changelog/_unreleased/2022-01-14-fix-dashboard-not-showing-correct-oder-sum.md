---
title: Dashboard not showing correct order sum
issue: NEXT-19604
author: Daniela Puetz
author_github: @PuetzD
---
# Administration
* Use technicalName instead of name since name needs the state_machine_translation table, also the translated value might not actually be equal to "paid", thus we should use the technicalName field directly

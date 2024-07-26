---
title: Improve admin component override logic
issue: NEXT-00000
author: Benjamin Wittwer
author_email: benjamin.wittwer@a-k-f.de
author_github: akf-bw
---
# Administration
* Changed `async-component.factory.ts` to sort overrides by their `overrideIndex`, regardless of when they were registered.
* Changed `template.factory.ts` to sort overrides by their `index`, regardless of when they were registered.

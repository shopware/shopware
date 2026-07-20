---
title: Use single shared interval for sw-time-ago component
author: Benjamin Wittwer
author_email: Discord.Benjamin@web.de
author_github: gecolay
---
# Administration
* Changed `Resources/app/administration/src/app/component/utils/sw-time-ago/index.ts` to use the `useUpdateClock` function, which subscribes to a single shared time update interval

---
title: Fix time date type formatting in datepicker
issue: NEXT-16349
author: d.neustadt
author_email: d.neustadt@shopware.com 
author_github: dneustadt
---
# Administration
* Changed computed `timezoneFormattedValue` to early return value if date type is time in format `H:i` and can't be converted to zoned time

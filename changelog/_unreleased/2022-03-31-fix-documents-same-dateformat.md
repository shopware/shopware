---
title: Fix documents same dateformat
issue: NEXT-13089
author: Thomas Wunner
author_email: acc@wunner-software.de
author_github: @alpham8
---
# Core
* changed behavior of delivery date locale date formatting to retrieve the right formatting from `order.language.locale` as all the other dates in company's address block on the right by keeping the inheritance chain intact

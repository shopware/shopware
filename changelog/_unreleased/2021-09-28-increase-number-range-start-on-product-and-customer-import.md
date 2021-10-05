---
title: Increase number range start on product and customer import
issue: NEXT-12710
author: d.neustadt
author_email: d.neustadt@shopware.com 
author_github: dneustadt
---
# Core
* Added `UpdateNumberRangesSubscriber` to increase start number of global number ranges for products and customers if the provided number matches the defined pattern

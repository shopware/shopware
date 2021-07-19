---
title: Allow sorting by exclusive price of currency
issue: NEXT-15851
author: d.neustadt
author_email: d.neustadt@shopware.com 
author_github: dneustadt
---
# Core
* Changed handling of price field accessor to respect provided currency id and tax state provided in the form of e.g `price.net` or `price.343ba7e2da2946e5be2eae2e2d02aa90.gross`

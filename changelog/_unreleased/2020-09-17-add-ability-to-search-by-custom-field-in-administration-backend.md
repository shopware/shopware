---
title: Add ability to search by Custom Fields in Administration Backend
issue: NEXT-10855
author: Mroczny Czesiek, Wojciech Milek
author_email: mrocznyczesiek@gmail.com, wojciechus@hotmail.com 
author_github: @MroczyCzesiek, @wojciechus
---
# Core
* Added `SearchRanking` flag to `customFields` in `ProductDefinition`, `CustomerDefinition` and `ProductManufacturerDefinition`
* It has no impact on the Storefront search functionality
* Tested on the shop with 6000+ products - no significant impact on search performance

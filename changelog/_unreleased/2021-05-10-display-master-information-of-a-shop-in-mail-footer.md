---
title: Display master information of a shop in mail footer
issue: NEXT-12852
author: Niklas Limberg
author_email: n.limberg@shopware.com
author: NiklasLimberg
author_github: NiklasLimberg
---
# Core
* Added a default mail footer which displays the `core.basicInformation.address` and `core.basicInformation.bankAccount` config values
* Changed the `twig` config filter, to make  it sales-channel aware in mail templates
___
# Administration
* Added `text-editor` elements to the basic information settings page for the config keys `core.basicInformation.address` and `core.basicInformation.bankAccount`
* Changed the `sw-system-config` component, so that it can display `text-editor` elements

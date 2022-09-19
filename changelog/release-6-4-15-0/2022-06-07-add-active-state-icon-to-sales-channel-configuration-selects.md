---
title: Add active state icon sales channel configuration selects and add preview slot for selects
issue: NEXT-21916
author: Joshua Behrens
author_email: code@joshua-behrens.de
author_github: @JoshuaBehrens
---
# Administration
* Added slot `preview` to `sw-select-result` to have a slot before the text for easier object placement and similar code structure to table cells
* Added slot `result-label-preview` to `sw-entity-multi-select` to expose the `preview` slot from `sw-select-result` like the `result-label-property` slot
* Added active state icon to `sw-sales-channel-defaults-select` as preview for the result item depending on new property `shouldShowActiveState` (defaults to false)
* Changed `shouldShowActiveState` for payment method, country and shipping method configurations in `sw-sales-channel-detail-base`
* Changed sorting of payment methods in `sw-sales-channel-detail-base` for payment methods selection from `position ASC` to `active DESC, position ASC`

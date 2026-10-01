---
title: Adjust card component position name in order detail
issue: NEXT-40064
---
# Administration
* Added in `src/module/sw-order/component/sw-order-details-state-card/sw-order-details-state-card.html.twig`
  * Added props `position` to make card has different position identifier.
  * Added computed `cardPostion` to make card has different position identifier.
* Changed `position-identifier` with `cardPosition` computed in `src/module/sw-order/component/sw-order-details-state-card/sw-order-details-state-card.html.twig`
* Added `position` props for each `sw-order-details-state-card` in `src/module/sw-order/view/sw-order-detail-details/sw-order-detail-details.html.twig`

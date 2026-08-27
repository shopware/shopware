---
title: Stop offering shipping methods without a price matrix
issue: 19001
author: Daniel Vien
author_email: d.vienphamtri@shopware.com
---
# Core
* Added a price filter to `Shopware\Core\Checkout\Shipping\SalesChannel\ShippingMethodRoute` so that `onlyAvailable=1` no longer returns active shipping methods whose prices cannot resolve a cost — an empty matrix, or rows that all lack currency values. One usable row is enough, and requests without the flag are unchanged.
* Added a guard to `Shopware\Core\Checkout\Shipping\Validator\ShippingMethodValidator` that rejects writes leaving an active shipping method without a usable price (`active_shipping_method_without_price`). Removing, reassigning or emptying the last usable `shipping_method_price`, or activating a method without one, now returns a `400`. Creating a method without prices still works; to remove a matrix, deactivate the method in an earlier request first.

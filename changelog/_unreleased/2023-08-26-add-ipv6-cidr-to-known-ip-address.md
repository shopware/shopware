---
title: Add IPv6 CIDR to known IP address for maintenance allow list suggestions
issue: NEXT-24962
author: Joshua Behrens
author_email: code@joshua-behrens.de
author_github: @JoshuaBehrens
---
# API
* Changed `/api/_admin/known-ips` to now also return IPv6 CIDR
___
# Administration
* Changed `\Shopware\Administration\Framework\Routing\KnownIps\KnownIpsCollector::collectIps` to also return common IPv6 CIDR masks
* Added snippets `global.sw-multi-tag-ip-select.knownIps.youIPv6Block64`, `global.sw-multi-tag-ip-select.knownIps.youIPv6Block56` and `global.error-codes.SHOPWARE_INVALID_IP_CIDR` for IPv6 CIDR situations
* Added new attribute `errorCode` to `sw-multi-tag-ip-select` to change the kind of error, that is display on an invalid entry
* Changed response filter in method `validKnownIps` in component `sw-multi-tag-ip-select` to validate the same way as manual entries are validated
* Added `isValidCidr` to JS string utils
* Changed `sw-multi-tag-ip-select` for maintenance configuration in `sw-sales-channel-detail-base` to allow CIDR entries

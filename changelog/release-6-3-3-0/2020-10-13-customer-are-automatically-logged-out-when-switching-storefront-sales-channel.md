---
title: Customer are automatically logged out when switching storefront sales channel
issue: NEXT-11153
---
# Storefront
*  Added `sw-sales-channel-id` into the session when user browsing storefront sales channel.
*  Added sales channel checking in `Shopware\Storefront\Framework\Routing\StorefrontSubscriber` to automatically logged out customer if the request sales channel does not match with the stored session sales channel.

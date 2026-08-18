---
title: Administration password recovery links use a trusted origin
issue: #304
---
# Core
* Changed Administration password recovery links to be built from `APP_URL` when no trusted hosts are configured. If trusted hosts are configured, Shopware can continue to use the request host after Symfony has validated it. Ensure that `APP_URL` contains the public HTTP or HTTPS URL of the shop.

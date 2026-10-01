---
title: Use valid text input types in checkout address modals
issue: 18123
author: Daniel Vien
author_email: d.vienphamtri@shopware.com
---
# Storefront
* Changed `address-manager-modal-create-address.html.twig` to prevent the billing or shipping address type from leaking into shared form inputs when `ACCESSIBILITY_TWEAKS` is enabled.

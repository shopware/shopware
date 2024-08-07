---
title: Remove hidden input field for requestedGroupId when it equals to the sales channels default groupId
issue: NEXT-00000
author: Melvin Achterhuis
author_email: melvin.achterhuis@gmail.com
author_github: @MelvinAchterhuis
---

# Storefront
* Added the following twig block in `customer-group-register.html.twig`
    * `component_account_register_requested_group_id`
* Removed the hidden input field for `requestedGroupId` when it equals the sales channels default groupId

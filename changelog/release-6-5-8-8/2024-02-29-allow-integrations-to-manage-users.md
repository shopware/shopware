---
title: Allow integrations to manage users
issue: NEXT-33078
---

# Core
* Changed `\Shopware\Core\Framework\Api\Controller\UserController` to only validate `user-verified` scope for password grant types from the administration, thus allowing integrations to manage Users and Roles over API.

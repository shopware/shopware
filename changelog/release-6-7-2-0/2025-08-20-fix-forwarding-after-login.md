---
title: Fix forwarding after login
issue: #11906
---
# Administration
* Changed `handleLoginSuccess` in login component to properly await `$router.push()` call, thus correctly forwarding to the original requested path after successful login.

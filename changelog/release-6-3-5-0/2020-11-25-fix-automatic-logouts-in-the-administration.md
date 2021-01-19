---
title: Fix automatic logouts in the administration
issue: NEXT-10663
author: Jannis Leifeld
author_email: jannis.leifeld@googlemail.com 
author_github: @jleifeld
---
# Administration
* Changed handling of `isLoggedIn` in `loginService` to fix automatic logouts even when refresh token is still valid

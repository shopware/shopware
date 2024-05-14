---
title: Throw an exception when an app uses features that require a secret but does not provide an app secret
issue: NEXT-25490
---
# Core
* Changed app install/update to throw an exception when in dev mode if features requiring app secret are specified but there is no app secret

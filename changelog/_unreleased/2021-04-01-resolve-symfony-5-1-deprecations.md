---
title: Resolve Symfony 5.1 deprecations
issue: NEXT-14633
author: Dominik Brader
author_email: dominik@brader.co.at
author_github: @TheKeymaster
---
# Core
* Changed `Core/Framework/Resources/config/packages/dev/routing.yaml` and `Core/Framework/Resources/config/packages/routing.yaml` to address the Symfony 5.1 routing deprecation, by explicitly setting `framework.router.utf8` to `true`.

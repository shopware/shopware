---
title: Context object improvements
issue: NEXT-37399
---

# Core

* Changed `\Shopware\Core\Framework\Context` to allow only \Closure in `enableInheritance`, `disableInheritance` and `scope` method to prevent misuse of the context object in sandbox environments.

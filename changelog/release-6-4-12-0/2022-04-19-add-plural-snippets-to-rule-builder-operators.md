---
title: add plural snippets to rule builder operators
issue: NEXT-20001
author: Malte Janz
author_email: m.janz@shopware.com
author_github: MalteJanz
___
# Administration
* Added boolean property `plural` to `sw-condition-operator-select` component to dynamically choose plural snippets instead of singular
* Changed all condition templates where plural snippets are expected and pass the corresponding property to `sw-condition-operator-select`
* Changed all corresponding snippets under `global.sw-condition.operator.x` to also supply plural versions if applicable

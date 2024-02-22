---
title: SeoUrlPlaceholderHandler - removed unnecessary param from method call
issue: 00000
author: Matheus Gontijo
author_email: matheus@matheusgontijo.com
author_github: matheusgontijo
---
Simply removed unnecessary param from method call on `SeoUrlPlaceholderHandler` class.

```
-$path = $this->router->generate($name, $parameters, RouterInterface::ABSOLUTE_PATH);
+$path = $this->router->generate($name, $parameters);
```

The third param `RouterInterface::ABSOLUTE_PATH` is already the same by default. So, we don't need it anyways.

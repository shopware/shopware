---
title: Add strict mode for list fields
issue: NEXT-15170
author: Timo Altholtmann
 
---
# Core
*  Added property `strict` to the `Listfield`. If set to true, the `ListField->decode()` will always return a non associative array.
___
# Upgrade Information
## ListField strict mode
A `ListField` will now always return a non associative array if the strict mode is true. This will be the default in 6.5.0. Please ensure that the data is saved as non associative array or switch to `JsonField` instead.

Valid `listField` before: 
```
Array
(
    [0] => baz
    [foo] => bar
    [1] => Array
        (
            [foo2] => Array
                (
                    [foo3] => bar2
                )
        )
)
```

Valid `ListField` after:
```
Array
(
    [0] => baz
    [1] => bar
    [2] => Array
        (
            [foo2] => Array
                (
                    [foo3] => bar2
                )
        )
)
```

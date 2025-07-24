---
title: Add optional callback parameter to `addAssociation` method in Criteria
author: Mirzorasul Danierov
author_email: dmirzorasul@gmail.com
author_github: @mdanierov
---

The `addAssociation` method in the `Criteria` class now supports an optional callback parameter.  
This allows developers to immediately modify the final nested `Criteria` object during association chaining.

Example usage:

```php
$criteria->addAssociation('products.media', function (Criteria $mediaCriteria) {
    $mediaCriteria->addFilter(...);
});

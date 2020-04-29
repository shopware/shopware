[titleEn]: <>(Read)
[hash]: <>(article:dal_read)

Reading entities from the storage is pretty straight-forward and does not require any special criteria object unless
you already know what you are looking for.

The entity reader always works in batch mode, which means that you should not request entities one by one.

## Reading entities

The entity repositories provide a `search()` method which takes two arguments:

1. The `Criteria` object, which holds a list of ids in hex format.
2. The `Context` object to be read with.

```php
/** @var \Shopware\Core\Content\Product\ProductCollection $products */
$products = $productRepository->search(
    new Criteria([
        'f8d36562c5614c5994aecb9c73d2b13e',
        '67a8a047b638493d95bb2a4cdf351cf3',
        'b94055962e4b49ceb86f55f8d1932607',
    ]),
    $context
)->getEntities();
```

The return value is a search result which has the entity collection containing all found entities as hydrated objects.

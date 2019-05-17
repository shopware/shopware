[titleEn]: <>(Breaking change - Refactor line item)

The `LineItem` has been refactored. The property `key` has been renamed to `id` to be a bit more consistent
with other structs/entities.

The id must be a unique string. In most cases you use the id of the entity which the line item refers to.
If you want to have different line items of the same entity (e.g. different prices, children), you must use a different id.

Previously you often had to do the following:
```php
<?php
$lineItem = new LineItem('98432def39fc4624b33213a56b8c944d', 'product');
$lineItem->setPayload(['id' => '98432def39fc4624b33213a56b8c944d']);

// New way:

$lineItem = new LineItem('98432def39fc4624b33213a56b8c944d', 'product', '98432def39fc4624b33213a56b8c944d');
```

All tests and the documentation has been updated.
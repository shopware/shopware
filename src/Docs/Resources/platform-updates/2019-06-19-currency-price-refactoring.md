[titleEn]: <>(Currency price refactoring)

The structure of the extended product prices has been changed. The gradings can no longer be defined for each currency, but are valid for all currencies per rule.
The following things have been changed:

* `PriceField` now expects an array with the following values:
```
    'currencyId' => [new NotBlank(), new Uuid()],
    'gross' => [new NotBlank(), new Type('numeric')],
    'net' => [new NotBlank(), new Type('numeric')],
    'linked' => [new Type('boolean')],
```
* `PriceRuleFieldAccessorBuilder` renamed to `ListingPriceFieldAccessorBuilder`
* `ProductEntity::price` now returns a `PriceCollection`.

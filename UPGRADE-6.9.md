# 6.9.0.0

# Changed Functionality

<details>

## Nullable order reference on `DocumentEntity`

The order reference on `Shopware\Core\Checkout\Document\DocumentEntity` became nullable. `getOrderId()` and `getOrderVersionId()` returned `?string` instead of `string`; documents that are not based on an order returned `null`.

`DocumentEntity::setOrderId()` and `setOrderVersionId()` accepted `?string`. Extensions overriding these setters had to widen their parameter types accordingly.

</details>

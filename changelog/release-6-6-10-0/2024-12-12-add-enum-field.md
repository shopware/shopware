---
title: Add enum field to DAL
issue: NEXT-39968
---
# Core
* Added support for Enum fields in the Data Abstraction Layer (DAL) to store and query enum values.

___
# Upgrade Information

The new field type allows to use PHP's `BackedEnum` types to be used as Entity fields. Together with RDBMS `ENUM` types, this allows to store and query enum values in a type-safe way with restricted values for a field.

## Example

```php
<?php
// Declare your Enum
enum PaymentProvider: string {
    case PAYPAL = 'paypal';
    case CREDIT_CARD = 'credit_card';
}

// Assign the Enum to a field
class Entity {
    private PaymentProvider $paymentProvider;
…
}

// Define the field in the EntityDefinition
class EntityDefinition extends EntityDefinition {
…
    public function getFields(): FieldCollection {
        return new FieldCollection([
            new EnumField('paymentProvider', 'payment_provider', PaymentProvider::CREDIT_CARD),
        ]);
    }
}
```

```mysql
CREATE TABLE `entity`
(
    `id`               INTEGER                        NOT NULL,
    `payment_provider` ENUM ('paypal', 'credit_card') NOT NULL
)
```

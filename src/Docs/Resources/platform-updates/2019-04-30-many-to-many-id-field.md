[titleEn]: <>(New ManyToManyIdField)

The new `\Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyIdField` allows to store ids of an `\Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField` inside the entity.

## How to implement

1. Add the field to the entity definition class:
    
    `new ManyToManyIdField('property_ids', 'propertyIds', 'properties')`
    
    The third parameter has to be the property name of the related association
    
    `(new ManyToManyAssociationField('properties', ...`

2. Add property, getter and setter to entity class
```
    /**
     * @var array|null
     */
    protected $propertyIds;

    public function getPropertyIds(): ?array
    {
        return $this->propertyIds;
    }

    public function setPropertyIds(?array $propertyIds): void
    {
        $this->propertyIds = $propertyIds;
    }
    
```

The DAL will detect this field automatically and updates the data each time the entity changed.

## When do i really need this field?

This field is required for a special kind of filter. The above example shows the relation between a `product` and its `properties`.
Adding this field to the product definition allows to send the following requrest to the DAL:

**select all products which has the property `red` or `green` AND `xl` or `l`**

```
$criteria = new Criteria();
$criteria->addFilter(
    new EqualsAnyFilter('product.propertyIds', ['red-id', 'green-id'])
);
$criteria->addFilter(
    new EqualsAnyFilter('product.propertyIds', ['xl-id', 'l-id'])
);
```

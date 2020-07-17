[titleEn]: <>(Field Accessor)
[hash]: <>(article:dal_field_accessor)

A FieldAccessor is a selector for the data in your storage.

In general, there is no need for a custom FieldAccessor unless you are dealing with uncommon data structures
like JSON fields. For that kind of data structures, you need to decide which value should be used for searching
entities in your storage.

## Example

Let's look at our `PriceField`. It has a defined data structure with `gross`, `net` and `linked` properties. If you
want to search on the `PriceField` you cannot simply evaluate a JSON field as you cannot compare it to anything.
Therefore you need to classify your data and select your value to be compared. In this case, it will be the `gross`
property in the `PriceField`.

The FieldAccessor must implement the `Shopware\Core\Framework\DataAbstractionLayer\Dbal\FieldAccessorBuilder\FieldAccessorBuilderInterface` interface and should be registered and tagged in the service container as
`shopware.field_accessor_builder`.

```php
class PriceFieldAccessorBuilder implements FieldAccessorBuilderInterface
{
    public function buildAccessor(string $root, Field $field, Context $context, string $accessor): ?string
    {
        if (!$field instanceof PriceField) {
            return null;
        }

        return sprintf('(CAST(JSON_UNQUOTE(JSON_EXTRACT(`%s`.`%s`, "$.gross")) AS DECIMAL))', $root, $field->getStorageName());
    }
}
```

The interface provides one method which, in case it can handle the given field, should return a selector to the
field. Using JSON in SQL can be quite complex, so you have to extract, unquote and cast the value to a correct format.

The returned selector will be used in the SQL statement for comparing or selecting the data.

```xml
<service id="Shopware\Core\Framework\DataAbstractionLayer\Dbal\FieldAccessorBuilder\PriceFieldAccessorBuilder">
    <tag name="shopware.field_accessor_builder" />
</service>
```

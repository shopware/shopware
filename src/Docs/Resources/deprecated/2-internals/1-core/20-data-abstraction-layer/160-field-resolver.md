[titleEn]: <>(Field Resolver)
[hash]: <>(article:dal_field_resolver)

A FieldResolver is a specific implementation for a field, which needs some extra code to be found properly.

Most use-cases are cover by the standard FieldResolver which handle every kind of relation.
In fact, the fields `OneToMany`, `ManyToOne` and `ManyToMany` are handled by their corresponding FieldResolver to
handle the JOINs in relational database systems.

Imagine you have a list of SEO urls and one of them is marked as canonical. If you create a field `canonicalUrl` to
return the SEO url marked as canonical, your JOIN needs the additional condition to filter on `is_canonical = 1` to
only find the canonical URL. With a custom `FieldResolver`, you are free to design the JOIN yourself.

## Example

A custom FieldResolver must implement the `Shopware\Core\Framework\DataAbstractionLayer\Dbal\FieldResolver\FieldResolverInterface`
interface and should be registered and tagged in the service container as `shopware.field_resolver`.

```php
class CanonicalUrlFieldResolver implements FieldResolverInterface
{
    public function resolve(string $definition, string $root, Field $field, QueryBuilder $query, Context $context, EntityDefinitionQueryHelper $queryHelper, bool $raw): bool
    {
        if (!$field instanceof CanonicalUrlField) {
            return false;
        }

        $seoUrlAlias = $root . '.' . $field->getPropertyName();

        $parameters = [
            '#root#' => EntityDefinitionQueryHelper::escape($root),
            '#source_column#' => EntityDefinitionQueryHelper::escape($field->getStorageName()),
            '#alias#' => EntityDefinitionQueryHelper::escape($seoUrlAlias),
            '#reference_column#' => EntityDefinitionQueryHelper::escape($field->getReferenceField()),
        ];

        $condition = str_replace(
            array_keys($parameters),
            array_values($parameters),
            '#alias#.#reference_column# = #root#.#source_column# AND #alias#.is_canonical = 1'
        );

        $query->leftJoin(
            EntityDefinitionQueryHelper::escape($root),
            EntityDefinitionQueryHelper::escape(SeoUrlDefinition->getEntityName()),
            EntityDefinitionQueryHelper::escape($seoUrlAlias),
            $condition
        );

        return true;
    }
}
```

The `resolve()` method should return if it was able to handle the field by returning `true` or `false`.

```xml
<service id="Shopware\Storefront\Api\Entity\Dbal\CanonicalUrlAssociationFieldResolver">
    <tag name="shopware.field_resolver" />
</service>
```

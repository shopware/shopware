[titleEn]: <>(M:N mapping)
[hash]: <>(article:dal_mapping)

M:N mapping represents a special case for the Data Abstraction Layer. If a table just represents the connection of two Entities and does not contain custom data your definition should extend `\Shopware\Core\Framework\DataAbstractionLayer\MappingEntityDefinition`. If you use this there are several advantages:

* Automatic resolving of associations
* No Entity class
* No collection class
* Update, delete and create are managed through the Data Abstraction Layer
* The API will hide this relation since it is only an implementation detail

### Set up the Fields

For this example we use the `Order` to `Tag` relationship as an example. In the `OrderDefinition` under `defineFields()` you find a Field of the type `ManyToManyAssociationField`.

```php
...,
new ManyToManyAssociationField(
    'tags', // Field name 
    TagDefinition::class, // Related Entity 
    OrderTagDefinition::class, // Mapping
    'order_id', // Local field name
    'tag_id' // Foreign field name
),
...,
```

By this field the Data Abstraction Layer determines the model to load (`Tag`) and the RelationTable to use for the association (`OrderTag`). You find the exact same definition on the **reverse side** in the `TagDefinition`:

```php
...,
new ManyToManyAssociationField(
    'orders', // Field name
    OrderDefinition::class, // Related entity
    OrderTagDefinition::class, // Mapping
    'tag_id', // Local field
    'order_id' // Foreign field name
),
...,
```

### Define the mapping

In order to resolve the field the Data Abstraction Layer now needs a `MappingEntityDefinition` called `OrderTagDefinition` and containing the fields `tag_id` and `order_id`. Like this:

```php
class OrderTagDefinition extends MappingEntityDefinition
{
    public function getEntityName(): string
    {
        return 'order_tag';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new FkField('tag_id', 'tagId', TagDefinition::class))->addFlags(new PrimaryKey(), new Required()),
            (new FkField('order_id', 'orderId', OrderDefinition::class))->addFlags(new PrimaryKey(), new Required()),
            new ManyToOneAssociationField('tag', 'tag_id', TagDefinition::class, 'id', false),
            new ManyToOneAssociationField('order', 'order_id', OrderDefinition::class, 'id', false),
        ]);
    }
}
```


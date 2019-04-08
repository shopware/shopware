# Data inheritance

## InheritanceAware
The Shopware\Core DataAbstractionLayer allows to define a data inheritance inside a single entity.
It requires the following configurations:
* `Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition::getParentPropertyName` returns the property name of the parent association
```
public static function getParentPropertyName(): string
{
    return 'parent';
}

```
* A parent child association is configured:
```
new FkField('parent_id', 'parentId', self::class),
new ParentAssociationField(self::class, false))->addFlags(new WriteOnly(),
new OneToManyAssociationField('children', self::class, 'parent_id', 'id'))->addFlags(new CascadeDelete(),
```

If all requirements are fulfilled, each field (even associations) can be flagged with `Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Inherited`.
In case that a field with this flag is not filled (IS NULL or Association are empty), the DataAbstractionLayer uses the parent row to solve the field value.

The following examples shows a simple usage of such an inheritance:
```
<?php

namespace Test;

class HumanDefinition extends EntityDefinition
{
    public static function getFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            new FkField('parent_id', 'parentId', self::class),
            new StringField('name', 'name'),
            (new StringField('last_name', 'lastName'))->addFlags(new Inherited()),
            new ParentAssociationField(self::class, false),
            new OneToManyAssociationField('children', self::class, 'parent_id', 'id'),
        ]);
    }
}
``` 

## Simple field inheritance
Now we create a parent and a child in the storage:

```
/** @var EntityRepositoryInterface $repo */
$repo = $this->get(HumanRepository::class);

$parentId = Uuid::randomHex();
$childId = Uuid::randomHex();

$repo->create([
    [
        'id' => $parentId,
        'name' => 'Father',
        'lastName' => 'Family name'
    ],
    [
        'id' => $childId,
        'parentId' => $parentId,
        'name' => 'Child'
    ]
], ShopContext::createDefaultContext());
```

Now we can query and read the data over the repository:
```
/** @var EntityRepositoryInterface $repo */
$repo = $this->get(HumanRepository::class);

$humans = $repo->read(new Criteria([$parentId, $childId]), ShopContext::createDefaultContext());

$parent = $humans->get($parentId);
$child = $humans->get($childId);

```

This is a stripped var dump of both objects:
```
object(Human)#1 (2) {
  ["name":protected]=>
  string(6) "Father"
  ["lastName":protected]=>
  string(11) "Family name"
}
object(Human)#2 (2) {
  ["name":protected]=>
  string(4) "Child"
  ["lastName":protected]=>
  string(11) "Family name"
}
``` 

The DataAbstractionLayer also allows to query this information in search requests:
```
/** @var EntityRepositoryInterface $repo */
$repo = $this->get(HumanRepository::class);

$criteria = new Criteria();
$criteria->addFilter(new EqualsFilter('human.lastName', 'Family name'))8878;

$result = $repo->search($criteria, ShopContext::createDefaultContext());

var_dump($result->getTotal());  //dumps "2"
```

## Association Inheritance
The DataAbstractionLayer also allows to configure inherited associations.

```
<?php

namespace Test;

class HumanDefinition extends EntityDefinition
{
    public static function getFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            //...
                        
            (new OneToManyAssociationField('pets', PetDefinition::class, 'human_id'))->addFlags(new CascadeDelete(), new Inherited())
        ]);
    }
}
```

The above `pets` associations defines that each human can have many pets. The inherited flag defines, if a `child` human do not have own defined pets, the DataAbstractionLayer will read the `pets` of the `parent`.
To support such associations the sql database table requires a field named `pets`. This field is used for the DataAbstractionLayer and can't be written by API or other tools. 

```
CREATE TABLE `human` (
  `id` binary(16) NOT NULL,
  `parent_id` binary(16) NULL DEFAULT NULL,
  `name` varchar(100),
  `last_name` varchar(100),
  
  #association property
  `pets` binary(16) NULL,   
  
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_product.parent_id` FOREIGN KEY (`parent_id`) REFERENCES `human` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

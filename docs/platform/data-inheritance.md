# Data inheritance

The Shopware ORM allows to define a data inheritance inside a single entity.
It requires the following configurations:
* `Shopware\Api\Entity\EntityDefinition::getParentPropertyName` returns the property name of the parent association
```
public static function getParentPropertyName(): string
{
    return 'parent';
}

```
* A parent child association is configured:
```
new FkField('parent_id', 'parentId', self::class),
new ManyToOneAssociationField('parent', 'parent_id', self::class, false))->setFlags(new WriteOnly(),
new OneToManyAssociationField('children', self::class, 'parent_id', false, 'id'))->setFlags(new CascadeDelete(),
```

If all requirements are fulfilled, each field (even associations) can be flagged with `Shopware\Api\Entity\Write\Flag\Inherited`.
In case that a field with this flag is not filled (IS NULL or Association are empty), the ORM uses the parent row to solve the field value.

The following examples shows a simple usage of such an inheritance:
```
<?php

namespace Test;

class HumanDefinition extends EntityDefinition
{
    public static function getFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->setFlags(new PrimaryKey(), new Required()),
            new FkField('parent_id', 'parentId', self::class),
            new StringField('name', 'name'),
            (new StringField('last_name', 'lastName'))->setFlags(new Inherited()),
            new ManyToOneAssociationField('parent', 'parent_id', self::class, false),
            new OneToManyAssociationField('children', self::class, 'parent_id', false, 'id'),
        ]);
    }
}
``` 

Now we create a parent and a child in the storage:

```
/** @var RepositoryInterface $repo */
$repo = $this->get(HumanRepository::class);

$parentId = Uuid::uuid4()->toString();
$childId = Uuid::uuid4()->toString();

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
/** @var RepositoryInterface $repo */
$repo = $this->get(HumanRepository::class);

$humans = $repo->readBasic([$parentId, $childId], ShopContext::createDefaultContext());

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

The ORM also allows to query this information in search requests:
```
/** @var RepositoryInterface $repo */
$repo = $this->get(HumanRepository::class);

$criteria = new Criteria();
$criteria->addFilter(new TermQuery('human.lastName', 'Family name'));

$result = $repo->search($criteria, ShopContext::createDefaultContext());

var_dump($result->getTotal());  //dumps "2"
```

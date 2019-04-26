[titleEn]: <>(Association loading)

With the latest DAL change, you are no longer able to auto-load `toMany` associations as they have a huge performance impact. From now on, please enrich your criteria object by adding associations like:

```php
$criteria->addAssociation('comments');
```
**Please think about when to load toMany associations and if they are really necessary there.**

Every `toOne` association will be fetched automatically unless you've disabled it. Some fields like the `ParentAssociationField` are disabled by default because they may lead to a circular read operation.

### AssociationInterface

The `AssociationInterface` has been removed in favor of the abstract `AssociationField` class because there were some useless type-hints in the code and it just feels right now.

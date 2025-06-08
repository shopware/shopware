---
title: Integrate custom entities
issue: NEXT-16225
---
# Core
* Changed `ArrayEntity::getVars()`, so that the `data` property is no longer in the payload but applied to the `root` level.
* Added `{app}/Resources/entities.xml` file, which allows to define custom entities inside apps.
* Changed `entitiyClass` expectation inside all fields and reference of entity definitions. It is now possible to provide the entity name instead
* Added new api endpoint `/api/custom-entity-{entityName}*` to handle crud api operations for all custom entities
* Added new domain `Core/System/CustomEntity` which contains all new classes for custom entities implementation, this includes:
  * database schema operations
  * XML parsing
  * DAL extensions
___
# Next Major Version Changes
## ArrayEntity::getVars():
* The `ArrayEntity::getVars()` has been changed so that the `data` property is no longer in the payload but applied to the `root` level.
  * This change affects all entity definitions that do not have their own entity class defined.
  * The API routes should not be affected, because they did not work with an ArrayEntity before the change, so no before/after payload can be shown.
  * before
  ```php 
  $entity = new ArrayEntity(['foo' => 'bar']);
  assert($entity->getVars(), ['data' => ['foo' => 'bar'], 'foo' => 'bar']);
  ```

  * after
  ```json 
  $entity = new ArrayEntity(['foo' => 'bar']);
  assert($entity->getVars(), ['foo' => 'bar']);
  ```
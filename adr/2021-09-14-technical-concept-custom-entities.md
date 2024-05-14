---
title: Technical concept custom entities
date: 2021-08-31
area: core
tags: [app, custom-entities, store-api, dal, admin-api]
---

## Context
It should be possible for apps to define their entities. Furthermore, it should be possible, if desired, that these entities are available via Store API.
Later, it should also be possible for store operators to create such entities. The concept is to consider that Apps can not add PHP code into the system under current circumstances. Also, a store operator is, seen from our point of view, not able to write PHP code himself to guarantee logic for his custom entities.
Therefore, purely through the definition of a custom entity, certain business logic should be automatically guaranteed.

## Decision

### Schema
* Definition
    * An app can include a `config/custom_entity.xml` file.
        * Multiple custom entities can be defined in the XML file.
    * Each custom entity, is registered with the prefix `custom_entity_` or the `ce_` shorthand.
        * App developers can then define that they would like to have `custom_entity_swag_blog` as an entity
        * To prevent naming collisions, app developers should always add their developer prefix to the entity name 
        * We then create the `custom_entity_swag_blog` table
* Tables / Properties / Columns:
    * A proper MySQL table is created for each custom entity.
    * For each custom entity field we create a real MySQL table column.
    * We support the following field data types:
        * All scalar fields (int, string, text, float, date, boolean)
        * All JSON fields (JSON, list, price, etc.)
        * All "linking" associations (many-to-one and many-to-many)
            * A bi-directional association will be left out for now.
        * one-to-one and one-to-many will be not supported for now.
* Install & Update
    * When installing and updating an app, the core automatically performs a schema update.
    * Consider running a `dal:validate` on the schema when installing and updating an app.
    * New fields on a custom entity must always be nullable or have a default
    * Changing a field/property data type is not allowed
    * If a field is no longer defined in the .xml file, it will be deleted from the database.
* Identification and representation
    * Each custom entity gets a `IdField(id)`, which serves as primary key
    * Each custom entity gets a field `TranslatedField(label)`, which is required and serves as display name for the admin

### Bootstrapping
* At kernel boot we load all custom entities from the database and register them in the registry and di-container.
* For each custom entity, an entity definition is registered
* A generic entity definition is used, which gets the property/column schema injected
* It must be checked how performant this is in case of bad performance we must put a cache in front of it (serialized properties/columns e.g.)
* If no database connection exists, a kernel boot should still be possible
* The loading of the custom entities for the kernel boot should be outsourced to a CustomEntityKernelLoader

### Api availability
For routing, we have to trick a bit, because currently for each entity in the system the routes defined exactly. This is not possible because the API route loader is triggered before the custom entities are registered. Therefore...
* We always register `/api/custom-entity-{entity}` as an API route and point to a custom controller that derives from ApiController.
* A request `/api/custom-entity-swag-blog`, then runs into our controller, and we get for the parameter `entity` the value `swag-blog`. We then pass this value to the parent method and prefetch it
* If the entity was defined with the `ce_` shorthand the API endpoints also use that shorthand, which means the route would be `/api/ce-{entity}`.

### Store api integration
* On the schema of the entity, the developer can define if this is `store_api_aware`.
* Entities which are not marked as `store_api_aware` will be removed from the response
* We will provide no automatic generated endpoint for the entities.
* Store api logics will be realized with the app-scripting epic

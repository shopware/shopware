---
title: Update rule assignment appearance
issue: NEXT-17034
author: Krispin Lütjann
author_email: k.luetjann@shopware.com 
author_github: King-of-Babylon
---
# Administration
* Added a new slot `selection-content` to `sw-data-grid` component
* Added a new slot `bulk-modal-delete-confirm-text` to `sw-entity-listing` component
* Changed `sendDeletions` function of `repository.data.js` to handle deletions via multiple primary keys
* Added new components
    * `sw-settings-rule-add-assignment-listing`
    * `sw-settings-rule-add-assignment-modal`
    * `sw-settings-rule-assignment-listing`
    * `sw-settings-rule-category-tree`
    * `sw-settings-rule-tree`
    * `sw-settings-rule-tree-item`
* Changed `sw-settings-rule-detail-assignment` component to add and delete rule assignments
___
# Upgrade Information
## New structure of the associationEntitiesConfig
We have added new properties to the associationEntitiesConfig to provide adding and deleting rule assignments, if wanted.
The whole structure should look like this:
```
{
    id: 'yourIdToIdentifTheData',
    notAssignedDataTotal: 0, // Total of not assigned data, this value will be automatically updated
    allowAdd: true, // Then you have to fill in the addContext
    entityName: 'yourEntityName',
    label: 'myNamespace.myLabel',
    criteria: () => { // The criteria to load the displayed data in the rule assignment
        const criteria = new Criteria();
        .....
        return criteria;
    },
    api: () => { // The context to load the data
        const api = Object.assign({}, Context.api);
        ...
        return api;
    },
    detailRoute: ...,
    gridColumns: [ // Definition of the columns in the rule assignment list
        {
            property: 'name',
            label: 'Name',
            rawData: true,
            sortable: true,
            routerLink: 'sw.product.detail.prices',
            allowEdit: false,
        },
        ...
    ],
    deleteContext: { // Configuration of the deletion
        type: 'many-to-many', // Types are many-to-many or one-to-many.
        entity: 'entityToDelete', // Entity which should be deleted / updated
        column: 'yourColumn', // Column in the entity to delete / update
    },
    addContext: { // Configuration of the addition
        type: 'many-to-many', // Types are many-to-many or one-to-many
        entity: 'entityToAdd', // Entity which should be added / updated
        column: 'yourColumn', // Column in the entity to add / update
        searchColumn: 'yourColumn', // Column which should be searchable
        criteria: () => { // Criteria to display in the add modal
            const criteria = new Criteria();
            ...
            return criteria;
        },
        gridColumns: [ // Definition of the columns in the add modal
            {
                property: 'name',
                label: 'Name',
                rawData: true,
                sortable: true,
                allowEdit: false,
            },
            ...
        ],
    },
},
```

## Extending the configuration

If you want to add a configuration or modify an existing one, you have to override the `sw-settings-rule-detail-assignments` component like this:

```
Component.override('sw-settings-rule-detail-assignments', {
    computed: {
        associationEntitiesConfig() {
            const associationEntitiesConfig = this.$super('associationEntitiesConfig');
            associationEntitiesConfig.push(...);
            return associationEntitiesConfig;
        },
    },
});
```

## Example for delete context
### One-to-many
```
deleteContext: {
    type: 'one-to-many',
    entity: 'payment_method',
    column: 'availabilityRuleId',
},
```

### Many-to-many
Important you have to add the association column to the criteria first:

```
criteria: () => {
    const criteria = new Criteria();
    criteria.setLimit(associationLimit);
    criteria.addFilter(Criteria.equals('orderRules.id', ruleId));
    criteria.addAssociation('orderRules');

    return criteria;
},
```

Then you have to use

```
deleteContext: {
    type: 'many-to-many',
    entity: 'promotion',
    column: 'orderRules',
},
```

### Deletion of extension values

If you want to delete an extension assignment, you have to include the extension path in the column value:

```
deleteContext: {
    type: 'many-to-many',
    entity: 'product',
    column: 'extensions.swagDynamicAccessRules',
},
```

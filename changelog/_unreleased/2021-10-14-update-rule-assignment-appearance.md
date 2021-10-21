---
title: Update rule assignment appearance
issue: NEXT-17034
flag: FEATURE_NEXT_16902
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
    label: ...,
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
        type: 'delete', // Types are delete or update. Delete for n:m associations and update for n:1 assocations.
        entity: 'entityToDelete', // Entity which should be deleted / updated
        column: 'yourColumn', // Column in the entity to delete / update
    },
    addContext: { // Configuration of the addition
        type: 'insert', // Types are insert or update. Insert for n:m assocations and update for n:1 assocations.
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

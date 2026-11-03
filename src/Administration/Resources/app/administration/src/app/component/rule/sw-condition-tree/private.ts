import { definePrivateComponent } from 'src/core/public-component';

/**
 * @sw-package fundamentals@after-sales
 *
 * The component is registered by its name for the Administration only: it never reaches extensions.
 * The lists hold everything it offered before it became a mini-module,
 * because Component.override inside the Administration could reach all of it. The files in `./src` are private to this module.
 */
/** @private */
export default definePrivateComponent({
    name: 'sw-condition-tree',
    load: () => import('./src/sw-condition-tree'),
    public: {
        props: [
            'conditionDataProviderService',
            'conditionRepository',
            'initialConditions',
            'rootCondition',
            'allowedTypes',
            'scopes',
            'associationField',
            'associationValue',
            'associationEntity',
            'childAssociationField',
            'disabled',
        ],
        emits: [
            'conditions-changed',
            'initial-loading-done',
        ],
        blocks: [
            'sw_condition_tree',
        ],
        computed: [
            'availableTypes',
            'rootId',
            'availableGroups',
            'restrictedConditions',
        ],
        methods: [
            'buildTree',
            'createTreeRecursive',
            'getRootNodes',
            'needsRootOrContainer',
            'applyRoot',
            'createCondition',
            'insertNodeIntoTree',
            'removeNodeFromTree',
            'validatePosition',
            'getDeletedIds',
            'getDeletedIdsRecursive',
            'emitChange',
        ],
    },
});

import template from './sw-condition-tree.html.twig';
import './sw-condition-tree.scss';

const { Component } = Shopware;
const { EntityCollection } = Shopware.Data;

/**
 * @private
 * @package business-ops
 */
Component.register('sw-condition-tree', {
    template,

    inject: [
        'feature',
    ],

    provide() {
        return {
            availableTypes: this.availableTypes,
            availableGroups: this.availableGroups,
            createCondition: this.createCondition,
            insertNodeIntoTree: this.insertNodeIntoTree,
            removeNodeFromTree: this.removeNodeFromTree,
            childAssociationField: this.childAssociationField,
            conditionDataProviderService: this.conditionDataProviderService,
            conditionScopes: this.scopes,
            restrictedConditions: this.restrictedConditions,
        };
    },

    props: {
        conditionDataProviderService: {
            type: Object,
            required: true,
        },

        conditionRepository: {
            type: Object,
            required: false,
            default: null,
        },

        initialConditions: {
            type: Array,
            required: false,
            default: null,
        },

        rootCondition: {
            type: Object,
            required: false,
            default: null,
        },

        allowedTypes: {
            type: Array,
            required: false,
            default: null,
        },

        scopes: {
            type: Array,
            required: false,
            default: null,
        },

        associationField: {
            type: String,
            required: true,
        },

        associationValue: {
            type: String,
            required: true,
        },

        associationEntity: {
            type: Object,
            required: false,
            default: null,
        },

        childAssociationField: {
            type: String,
            required: false,
            default: 'children',
        },

        disabled: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    data() {
        return {
            conditionTree: null,
            initialLoadingDone: false,
        };
    },

    computed: {
        availableTypes() {
            let conditions;
            if (this.allowedTypes) {
                conditions = this.allowedTypes.map((type) => {
                    return this.conditionDataProviderService.getByType(type);
                });
            } else {
                conditions = this.conditionDataProviderService.getConditions(this.scopes);
            }

            conditions.forEach(condition => {
                condition.translatedLabel = this.$tc(condition.label);
            });

            if (this.availableGroups) {
                conditions.sort((a, b) => a.translatedLabel.localeCompare(b.translatedLabel));

                const groupedConditions = [];
                this.availableGroups.forEach((group) => {
                    conditions.forEach((condition) => {
                        if (condition.group === group.id) {
                            groupedConditions.push(condition);
                        }

                        if (!condition.group && group.id === 'misc') {
                            groupedConditions.push(condition);
                            condition.group = 'misc';
                        }
                    });
                });

                return groupedConditions;
            }

            return conditions;
        },

        rootId() {
            return this.rootCondition !== null ? this.rootCondition.id : null;
        },

        availableGroups() {
            if (typeof this.conditionDataProviderService.getGroups !== 'function') {
                return [];
            }

            const groups = Object.values(this.conditionDataProviderService.getGroups());

            groups.forEach(group => {
                group.label = this.$tc(group.name);
            });

            groups.sort((a, b) => a.label.localeCompare(b.label));

            groups.map((group, index) => {
                if (group.id === 'misc') {
                    groups.splice(index, 1);
                    groups.push(group);
                }

                return groups;
            });

            return groups;
        },

        restrictedConditions() {
            if (typeof this.conditionDataProviderService.getRestrictedConditions !== 'function') {
                return [];
            }

            return this.conditionDataProviderService.getRestrictedConditions(this.associationEntity);
        },
    },

    watch: {
        initialConditions(newVal) {
            if (this.isNotDefined(newVal)) {
                this.conditionTree = null;
                return;
            }

            this.buildTree();
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            if (!this.isNotDefined(this.initialConditions)) {
                this.buildTree();
            }
        },

        buildTree() {
            const rootCondition = this.applyRootIfNecessary();
            this.conditionTree = this.createTreeRecursive(rootCondition, this.initialConditions);
            this.emitChange([]);
            if (!this.initialLoadingDone) {
                this.$emit('initial-loading-done');
                this.initialLoadingDone = true;
            }
        },

        createTreeRecursive(condition, conditions) {
            const children = conditions.filter(c => c.parentId === condition.id)
                .sort((a, b) => a.position - b.position)
                .map(c => this.createTreeRecursive(c, conditions))
                .filter(c => !condition[this.childAssociationField].has(c.id));

            condition[this.childAssociationField] = new EntityCollection(
                condition[this.childAssociationField].source,
                condition[this.childAssociationField].entity,
                condition[this.childAssociationField].context,
                null,
                [...children, ...condition[this.childAssociationField]],
            );
            return condition;
        },

        applyRootIfNecessary() {
            const rootNodes = this.initialConditions.filter((condition) => {
                return condition.parentId === this.rootId;
            });

            if (rootNodes.length === 1 && this.conditionDataProviderService.isOrContainer(rootNodes[0])) {
                return rootNodes[0];
            }

            const rootContainer = this.createCondition(
                this.conditionDataProviderService.getOrContainerData(),
                this.rootId,
                0,
            );

            // eslint-disable-next-line vue/no-mutating-props
            this.initialConditions.push(rootContainer);

            rootNodes.forEach(root => { root.parentId = rootContainer.id; });
            return rootContainer;
        },

        createCondition(conditionData, parentId, position) {
            let condition = this.conditionRepository.create(this.initialConditions.context);
            condition = Object.assign(
                condition,
                conditionData,
                {
                    parentId,
                    position,
                    [this.associationField]: this.associationValue,
                },
            );
            return condition;
        },

        insertNodeIntoTree(parentCondition, childToInsert) {
            if (!parentCondition) {
                throw new Error('[sw-condition-tree] Can not insert into non existing tree');
            }

            this.validatePosition(parentCondition, childToInsert);
            parentCondition[this.childAssociationField].forEach((child) => {
                if (child.position >= childToInsert.position) {
                    child.position += 1;
                }
            });

            parentCondition[this.childAssociationField].addAt(childToInsert, childToInsert.position);
            this.emitChange([]);
        },

        removeNodeFromTree(parentCondition, childToRemove) {
            if (!parentCondition) {
                throw new Error('[sw-condition-tree] Can not remove from non existing tree');
            }

            const deletedIds = this.getDeletedIds(childToRemove);

            parentCondition[this.childAssociationField].forEach((child) => {
                if (child.position > childToRemove.position) {
                    child.position -= 1;
                }
            });

            parentCondition[this.childAssociationField].remove(childToRemove.id);
            this.emitChange(deletedIds);
        },

        validatePosition(parentCondition, condition) {
            if (typeof condition.position !== 'number' || condition.position < 0) {
                condition.position = 0;
            }
            if (condition.position > parentCondition[this.childAssociationField].length) {
                condition.position = parentCondition[this.childAssociationField].length;
            }
        },

        getDeletedIds(condition) {
            const deletedIds = [];
            this.getDeletedIdsRecursive(condition, deletedIds);
            return deletedIds;
        },

        getDeletedIdsRecursive(condition, deletedIs) {
            if (!condition.isNew()) {
                deletedIs.push(condition.id);
                return;
            }

            condition[this.childAssociationField].forEach((child) => { this.getDeletedIdsRecursive(child, deletedIs); });
        },

        emitChange(deletedIds) {
            const conditions = new EntityCollection(
                this.initialConditions.source,
                this.initialConditions.entity,
                this.initialConditions.context,
                this.initialConditions.criteria,
                [this.conditionTree],
            );

            this.$emit('conditions-changed', {
                conditions,
                deletedIds,
            });
        },

        isNotDefined(val) {
            return val === null || typeof val === 'undefined';
        },
    },
});

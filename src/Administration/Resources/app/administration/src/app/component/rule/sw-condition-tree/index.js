import template from './sw-condition-tree.html.twig';
import './sw-condition-tree.scss';

const { Component } = Shopware;
const utils = Shopware.Utils;

/**
 * @private
 */
Component.register('sw-condition-tree', {
    template,

    data() {
        return {
            nestedConditions: {},
            associationStore: {},
            isApi: false
        };
    },

    provide() {
        return {
            conditionStore: this.conditionStore,
            entityAssociationStore: () => this.associationStore,
            config: this.config,
            isApi: () => this.isApi
        };
    },

    props: {
        entity: {
            type: Object,
            required: true
        },
        conditionStore: {
            type: Object,
            required: true
        },
        config: {
            type: Object,
            required: true
        },
        parentId: {
            type: String,
            required: false,
            default: null
        },
        entityAssociationStore: {
            type: Object,
            required: false,
            default: null
        },
        conditions: {
            type: Array,
            required: false,
            default: null
        }
    },

    created() {
        this.createdComponent();
    },

    beforeDestroy() {
        this.beforeDestroyComponent();
    },

    methods: {
        createdComponent() {
            this.associationStore = this.entityAssociationStore
                || this.entity.getAssociation(this.config.conditionIdentifier);

            if (this.conditions) {
                this.nestedConditions = this.checkRootContainer(
                    this.buildNestedConditions(this.conditions, this.parentId)
                );
                this.entity[this.config.conditionIdentifier] = [this.nestedConditions];
            } else {
                this.loadConditions();
            }

            this.$on('entity-save', this.onSave);
        },

        beforeDestroyComponent() {
            this.$off('entity-save');
        },

        onSave(loadConditions) {
            if (!loadConditions) {
                return;
            }

            this.loadConditions();
        },

        loadConditions() {
            this.associationStore.getList({
                page: 1,
                limit: 500,
                sortBy: 'position'
            }).then((conditionCollection) => {
                this.nestedConditions = this.checkRootContainer(
                    this.buildNestedConditions(conditionCollection.items, this.parentId)
                );
                this.entity[this.config.conditionIdentifier] = [this.nestedConditions];
            });
        },

        buildNestedConditions(conditions, parentId) {
            return conditions.reduce((accumulator, current) => {
                if (current.parentId === parentId) {
                    const children = this.buildNestedConditions(conditions, current.id);
                    children.forEach((child) => {
                        if (current[this.config.childName].indexOf(child) === -1) {
                            current[this.config.childName].push(child);
                        }
                    });

                    accumulator.push(current);
                }

                return accumulator;
            }, []);
        },

        checkRootContainer(nestedConditions) {
            if (nestedConditions.length === 1
                && this.config.isOrContainer(nestedConditions[0])) {
                if (nestedConditions[0][this.config.childName].length > 0) {
                    return nestedConditions[0];
                }

                nestedConditions[0][this.config.childName] = [
                    this.createCondition(
                        this.config.andContainer,
                        nestedConditions[0].id
                    )
                ];

                return nestedConditions[0];
            }

            const rootCondition = this.createCondition(this.config.orContainer, this.parentId);
            const subCondition = this.createCondition(
                this.config.andContainer,
                rootCondition.id,
                nestedConditions
            );
            rootCondition[this.config.childName] = [subCondition];

            if (!nestedConditions.length) {
                return rootCondition;
            }

            this.associationStore.removeById(rootCondition.id);
            this.associationStore.removeById(subCondition.id);
            this.associationStore.store = Object.assign(
                { [rootCondition.id]: rootCondition },
                { [subCondition.id]: subCondition },
                this.associationStore.store
            );

            return rootCondition;
        },

        createCondition(conditionData, parentId, children) {
            const conditionId = utils.createId();
            const condition = Object.assign(this.associationStore.create(conditionId), conditionData);
            condition.parentId = parentId;

            if (children) {
                children.forEach((child) => {
                    child.parentId = conditionId;
                });
                condition[this.config.childName] = children;
            }

            return condition;
        }
    }
});

import template from './sw-condition-base-line-item.html.twig';
import './sw-condition-base-line-item.scss';

const { Component } = Shopware;
const { EntityCollection } = Shopware.Data;

/**
 * @public
 * @package business-ops
 * @description Base line item condition for the condition-tree. This component must be a child of sw-condition-tree.
 * @status prototype
 * @example-type code-only
 * @component-example
 * <sw-condition-base-line-item :condition="condition"></sw-condition-base-line-item>
 */
Component.extend('sw-condition-base-line-item', 'sw-condition-base', {
    template,

    inject: [
        'feature',
        'insertNodeIntoTree',
        'removeNodeFromTree',
        'createCondition',
        'childAssociationField',
        'repositoryFactory',
        'conditionScopes',
        'unwrapAllLineItemsCondition',
    ],

    props: {
        parentCondition: {
            type: Object,
            required: false,
            default: null,
        },
    },

    computed: {
        ruleConditionRepository() {
            return this.repositoryFactory.create('rule_condition');
        },

        allowMatchesAll() {
            if (this.conditionScopes) {
                return this.conditionScopes.includes('cart');
            }

            return true;
        },

        matchesAllOptions() {
            return [
                {
                    value: false,
                    label: this.$tc('global.sw-condition.condition.lineItemCondition.any'),
                },
                {
                    value: true,
                    label: this.$tc('global.sw-condition.condition.lineItemCondition.all'),
                },
            ];
        },

        matchesAll: {
            get() {
                return this.parentCondition && this.parentCondition.type === 'allLineItemsContainer';
            },
            set(matchesAll) {
                if (matchesAll && this.parentCondition.type !== 'allLineItemsContainer') {
                    this.wrapCondition();

                    return;
                }

                if (!matchesAll && this.parentCondition.type === 'allLineItemsContainer') {
                    this.unwrapAllLineItemsCondition(this.condition);
                }
            },
        },
    },

    methods: {
        wrapCondition() {
            const child = this.createEntity(this.condition);

            this.removeNodeFromTree(this.parentCondition, this.condition);
            this.insertNodeIntoTree(
                this.parentCondition,
                this.createCondition(
                    {
                        type: 'allLineItemsContainer',
                        value: {},
                        [this.childAssociationField]: new EntityCollection(
                            this.condition[this.childAssociationField].source,
                            this.condition[this.childAssociationField].entity,
                            this.condition[this.childAssociationField].context,
                            null,
                            [child],
                        ),
                    },
                    this.parentCondition.id,
                    this.condition.position,
                ),
            );
        },

        createEntity(condition) {
            const entity = this.ruleConditionRepository.create();
            Object.keys(condition).forEach((key) => {
                if (key === 'id') {
                    return;
                }
                entity[key] = condition[key];
            });

            return entity;
        },
    },
});

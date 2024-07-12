import template from './sw-condition-all-line-items-container.html.twig';

const { Component, Mixin } = Shopware;
const { EntityCollection } = Shopware.Data;

/**
 * @private
 * @package services-settings
 * @description Contains some sw-base-conditions for matching all line items.
 * This component must be a child of sw-condition-tree
 * @status prototype
 * @example-type code-only
 * @component-example
 * <sw-condition-all-line-items-container :condition="condition" :level="0"></sw-condition-all-line-items-container>
 */
Component.register('sw-condition-all-line-items-container', {
    template,

    compatConfig: Shopware.compatConfig,

    emits: [
        'create-before',
        'create-after',
    ],

    provide() {
        return {
            unwrapAllLineItemsCondition: this.unwrapCondition,
        };
    },

    mixins: [
        Mixin.getByName('ruleContainer'),
    ],

    computed: {
        children() {
            return this.condition.children;
        },

        childType() {
            if (!this.children.first()) {
                return null;
            }

            return this.children.first().type;
        },
    },

    watch: {
        children() {
            if (this.children.length === 0) {
                this.removeNodeFromTree(this.parentCondition, this.condition);
            }
        },

        childType(type) {
            if (!type) {
                return;
            }

            const conditionType = this.conditionDataProviderService.getByType(type);
            const component = Component.getComponentRegistry().get(conditionType.component);

            if (component && component.extends !== 'sw-condition-base-line-item') {
                this.unwrapCondition(this.children.first());
            }
            this.setConditionValue();
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.setConditionValue();
        },

        setConditionValue() {
            this.condition.value = [];
            if (this.children.first().type === 'promotionLineItem') {
                this.condition.value = { type: 'promotion' };
                return;
            }

            if (this.children.first().type !== 'cartLineItemOfType') {
                this.condition.value = { type: 'product' };
            }
        },

        onAddPlaceholder() {},

        unwrapCondition(childCondition) {
            this.removeNodeFromTree(this.parentCondition, this.condition);
            this.insertNodeIntoTree(
                this.parentCondition,
                this.createCondition(
                    {
                        type: childCondition.type,
                        value: childCondition.value,
                        [this.childAssociationField]: new EntityCollection(
                            this.condition[this.childAssociationField].source,
                            this.condition[this.childAssociationField].entity,
                            this.condition[this.childAssociationField].context,
                            null,
                            [],
                        ),
                    },
                    this.parentCondition.id,
                    this.condition.position,
                ),
            );
        },

        onInsertBefore() {
            this.$emit('create-before');
        },

        onInsertAfter() {
            this.$emit('create-after');
        },
    },
});

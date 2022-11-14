import template from './sw-condition-and-container.html.twig';
import './sw-condition-and-container.scss';

const { Component, Mixin } = Shopware;

/**
 * @private
 * @package business-ops
 * @description Contains some sw-base-conditions / sw-condition-and-container connected by and.
 * This component must be a child of sw-condition-tree
 * @status prototype
 * @example-type code-only
 * @component-example
 * <sw-condition-and-container :condition="condition" :level="0"></sw-condition-and-container>
 */
Component.register('sw-condition-and-container', {
    template,

    inject: ['acl'],

    mixins: [
        Mixin.getByName('ruleContainer'),
    ],

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            if (this.nextPosition === 0) {
                this.onAddPlaceholder();
            }
        },

        onAddPlaceholder() {
            this.insertNodeIntoTree(
                this.condition,
                this.createCondition(
                    this.conditionDataProviderService.getPlaceholderData(),
                    this.condition.id,
                    this.nextPosition,
                ),
            );
        },

        onAddOrContainer() {
            const orContainer = this.createCondition(
                this.conditionDataProviderService.getOrContainerData(),
                this.condition.id,
                this.nextPosition,
            );

            this.insertNodeIntoTree(this.condition, orContainer);

            // "replace" first child if it is a placeholder
            if (this.condition[this.childAssociationField].length === 2 &&
                this.condition[this.childAssociationField][0].type === null) {
                this.removeNodeFromTree(this.condition, this.condition[this.childAssociationField][0]);
            }
        },

        onDeleteAll() {
            this.removeNodeFromTree(this.parentCondition, this.condition);
        },

        getNoPermissionsTooltip(role, showOnDisabledElements = true) {
            return {
                showDelay: 300,
                message: this.$tc('sw-privileges.tooltip.warning'),
                appearance: 'dark',
                showOnDisabledElements,
                disabled: this.acl.can(role),
            };
        },
    },
});

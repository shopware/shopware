import template from './sw-condition-or-container.html.twig';
import './sw-condition-or-container.scss';

const { Component, Mixin } = Shopware;

/**
 * @private
 * @package services-settings
 * @description Contains some sw-base-conditions / sw-condition-and-container connected by or.
 * This component must be a child of sw-condition-tree
 * @status prototype
 * @example-type code-only
 * @component-example
 * <sw-condition-or-container :condition="condition" :level="0"></sw-condition-or-container>
 */
Component.register('sw-condition-or-container', {
    template,

    compatConfig: Shopware.compatConfig,

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
            if (this.level === 0) {
                this.onAddAndContainer();
                return;
            }
            this.insertNodeIntoTree(
                this.condition,
                this.createCondition(
                    this.conditionDataProviderService.getPlaceholderData(),
                    this.condition.id,
                    this.nextPosition,
                ),
            );
        },

        onAddAndContainer() {
            const andContainer = this.createCondition(
                this.conditionDataProviderService.getAndContainerData(),
                this.condition.id,
                this.nextPosition,
            );

            this.insertNodeIntoTree(this.condition, andContainer);

            // "replace" first child if it is a placeholder
            if (this.condition[this.childAssociationField].length === 2 &&
                this.condition[this.childAssociationField][0].type === null) {
                this.removeNodeFromTree(this.condition, this.condition[this.childAssociationField][0]);
            }
        },

        onDeleteAll() {
            // if container is root container remove its children but not itself
            if (this.level === 0) {
                while (this.condition[this.childAssociationField].length > 0) {
                    this.removeNodeFromTree(this.condition, this.condition[this.childAssociationField][0]);
                }

                return;
            }

            // else remove container
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

import template from './sw-condition-or-container.html.twig';
import './sw-condition-or-container.scss';

const { Component } = Shopware;

/**
 * @private
 * @description Contains some sw-base-conditions / sw-condition-and-container connected by or.
 * This component must be a child of sw-condition-tree
 * @status prototype
 * @example-type code-only
 * @component-example
 * <sw-condition-or-container :condition="condition" :level="0"></sw-condition-or-container>
 */
Component.extend('sw-condition-or-container', 'sw-condition-and-container', {
    template,

    methods: {
        onAddChildClick() {
            this.createCondition(this.config.andContainer, this.nextPosition);
        },
        onAddAndClick() {
            if (this.level === 0) {
                this.onAddChildClick();
            } else {
                this.$super('onAddAndClick');
            }
        },
        onDeleteAll() {
            this.$super('onDeleteAll');

            if (this.level === 0) {
                this.$nextTick(() => {
                    this.onAddChildClick();
                });
            }
        }
    }
});

import { Component } from 'src/core/shopware';
import template from './sw-condition-or-container.html.twig';
import './sw-condition-or-container.less';

const AND_CONTAINER_NAME = 'swAndContainer';

/**
 * @public
 * @description TODO: Add description
 * @status prototype
 * @example-type code-only
 * @component-example
 * <sw-condition-or-container :condition="condition"></sw-condition-or-container>
 */
Component.extend('sw-condition-or-container', 'sw-condition-and-container', {
    template,

    methods: {
        onAddChildClick() {
            this.createCondition(AND_CONTAINER_NAME, this.nextPosition);
        },
        onAddAndClick() {
            if (this.level === 0) {
                this.onAddChildClick();
            } else {
                this.$super.onAddAndClick();
            }
        },
        onDeleteAll() {
            this.$super.onDeleteAll();

            if (this.level === 0) {
                this.$nextTick(() => {
                    this.onAddChildClick();
                });
            }
        }
    }
});

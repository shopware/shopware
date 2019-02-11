import template from './sw-condition-or-container.html.twig';
import AndContainer from '../sw-condition-and-container';
import './sw-condition-or-container.scss';

const AND_CONTAINER_NAME = 'andContainer';

/**
 * @public
 * @description TODO: Add description
 * @status prototype
 * @example-type code-only
 * @component-example
 * <sw-condition-or-container :condition="condition"></sw-condition-or-container>
 */
export default {
    name: 'sw-condition-or-container',
    extends: AndContainer,
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
};

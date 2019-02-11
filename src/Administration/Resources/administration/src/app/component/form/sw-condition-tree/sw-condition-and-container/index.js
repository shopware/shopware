import { Mixin } from 'src/core/shopware';
import template from './sw-condition-and-container.html.twig';
import './sw-condition-and-container.scss';

const PLACEHOLDER_NAME = 'placeholder';
const OR_CONTAINER_NAME = 'orContainer';
const AND_CONTAINER_NAME = 'andContainer';

/**
 * @public
 * @description TODO: Add description
 * @status prototype
 * @example-type code-only
 * @component-example
 * <sw-condition-and-container :condition="condition"></sw-condition-and-container>
 */
export default {
    name: 'sw-condition-and-container',
    template,
    mixins: [
        Mixin.getByName('validation'),
        Mixin.getByName('notification'),
        Mixin.getByName('condition')
    ],

    /**
     * All additional passed attributes are bound explicit to the correct child element.
     */
    inheritAttrs: false,

    computed: {
        containerRowClass() {
            return this.level % 2 ? 'container-condition-level__is--odd' : 'container-condition-level__is--even';
        },
        nextPosition() {
            const children = this.condition.children;
            if (!children || !children.length) {
                return 1;
            }

            return children[children.length - 1].position + 1;
        },
        sortedChildren() {
            if (!this.condition.children) {
                return [];
            }
            return this.condition.children.sort((child1, child2) => { return child1.position - child2.position; });
        },
        disabledDeleteButton() {
            // todo: make it standardized
            if (this.level === 0
                && this.condition
                && this.condition.children
                && this.condition.children.length === 1
                && this.condition.children[0].type === AND_CONTAINER_NAME
                && this.condition.children[0].children.length === 1
                && this.condition.children[0].children[0].type === PLACEHOLDER_NAME) {
                return true;
            }

            if (this.level === 1) {
                return this.parentDisabledDelete;
            }

            return false;
        }
    },

    watch: {
        condition() {
            this.createFirstPlaceholderIfNecessary();
        }
    },

    created() {
        this.createFirstPlaceholderIfNecessary();
    },

    methods: {
        createFirstPlaceholderIfNecessary() {
            if (!this.condition.children) {
                this.condition.children = [];
            }

            if (!this.condition.children.length) {
                this.createCondition(PLACEHOLDER_NAME, this.nextPosition);
            }
        },
        getComponent(type) {
            const condition = this.conditionStore.getByType(type);
            if (!condition) {
                return 'sw-condition-not-found';
            }

            return condition.component;
        },
        onAddAndClick() {
            this.createCondition(PLACEHOLDER_NAME, this.nextPosition);
        },
        onAddChildClick() {
            this.createCondition(OR_CONTAINER_NAME, this.nextPosition);
        },
        createCondition(type, position) {
            const condition = Object.assign(
                this.entityAssociationStore.create(),
                {
                    type: type,
                    parentId: this.condition.id,
                    position: position
                }
            );
            this.condition.children.push(condition);
        },
        createPlaceholderBefore(element) {
            const originalPosition = element.position;
            this.condition.children.forEach(child => {
                if (child.position < originalPosition) {
                    return;
                }

                child.position += 1;
            });

            this.createCondition(PLACEHOLDER_NAME, originalPosition);
        },
        createPlaceholderAfter(element) {
            const originalPosition = element.position;
            this.condition.children.forEach(child => {
                if (child.position <= originalPosition) {
                    return;
                }

                child.position += 1;
            });

            this.createCondition(PLACEHOLDER_NAME, originalPosition + 1);
        },
        onDeleteAll() {
            if (this.level === 0) {
                this.condition.children.forEach((child) => {
                    child.delete();
                    this.deleteChildren(child.children);
                });
            } else {
                this.deleteChildren(this.condition.children);
            }

            this.condition.children = [];

            this.$emit('delete-condition', this.condition);
        },
        deleteChildren(children) {
            children.forEach((child) => {
                if (child.children.length > 0) {
                    this.deleteChildren(child.children);
                }
                child.remove();
            });
        },
        onDeleteCondition(condition) {
            const originalPosition = condition.position;
            this.condition.children.forEach(child => {
                if (child.position < originalPosition) {
                    return;
                }

                child.position -= 1;
            });

            if (this.condition.children.length === 1) {
                this.onDeleteAll();
                return;
            }

            condition.delete();
            this.condition.children.splice(this.condition.children.indexOf(condition), 1);

            if (this.condition.children.length <= 0) {
                this.$nextTick(() => {
                    if (this.level === 0) {
                        this.onAddChildClick();
                    } else {
                        this.createCondition(PLACEHOLDER_NAME, this.nextPosition);
                    }
                });
            }
        }
    }
};

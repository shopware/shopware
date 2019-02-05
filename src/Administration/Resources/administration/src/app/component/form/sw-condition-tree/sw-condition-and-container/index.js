import { Mixin } from 'src/core/shopware';
import template from './sw-condition-and-container.html.twig';
import './sw-condition-and-container.scss';

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
            const children = this.condition[this.config.childName];
            if (!children || !children.length) {
                return 1;
            }

            return children[children.length - 1].position + 1;
        },
        sortedChildren() {
            if (!this.condition[this.config.childName]) {
                return [];
            }
            return this.condition[this.config.childName]
                .sort((child1, child2) => { return child1.position - child2.position; });
        },
        disabledDeleteButton() {
            // todo: make it standardized
            if (this.level === 0
                && this.condition
                && this.condition[this.config.childName]
                && this.condition[this.config.childName].length === 1
                && this.config.isAndContainer(this.condition[this.config.childName][0])
                && this.condition[this.config.childName][0][this.config.childName].length === 1
                && this.config.isPlaceholder(this.condition[this.config.childName][0][this.config.childName][0])) {
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
            if (!this.condition[this.config.childName]) {
                this.condition[this.config.childName] = [];
                return;
            }

            if (!this.condition[this.config.childName].length) {
                this.createCondition(this.config.placeholder, this.nextPosition);
            }
        },
        getComponent(condition) {
            return this.config.getComponent(condition);
        },
        onAddAndClick() {
            this.createCondition(this.config.placeholder, this.nextPosition);
        },
        onAddChildClick() {
            this.createCondition(this.config.orContainer, this.nextPosition);
        },
        createCondition(conditionData, position) {
            const condition = Object.assign(
                this.entityAssociationStore.create(),
                conditionData,
                {
                    parentId: this.condition.id,
                    position: position
                }
            );
            this.condition[this.config.childName].push(condition);
        },
        createPlaceholderBefore(element) {
            const originalPosition = element.position;
            this.condition[this.config.childName].forEach(child => {
                if (child.position < originalPosition) {
                    return;
                }

                child.position += 1;
            });

            this.createCondition(this.config.placeholder, originalPosition);
        },
        createPlaceholderAfter(element) {
            const originalPosition = element.position;
            this.condition[this.config.childName].forEach(child => {
                if (child.position <= originalPosition) {
                    return;
                }

                child.position += 1;
            });

            this.createCondition(this.config.placeholder, originalPosition + 1);
        },
        onDeleteAll() {
            if (this.level === 0) {
                this.condition[this.config.childName].forEach((child) => {
                    child.delete();
                    this.deleteChildren(child[this.config.childName]);
                });
            } else {
                this.deleteChildren(this.condition[this.config.childName]);
            }

            this.condition[this.config.childName] = [];

            this.$emit('delete-condition', this.condition);
        },
        deleteChildren(children) {
            children.forEach((child) => {
                if (child[this.config.childName].length > 0) {
                    this.deleteChildren(child[this.config.childName]);
                }
                child.remove();
            });
        },
        onDeleteCondition(condition) {
            const originalPosition = condition.position;
            this.condition[this.config.childName].forEach(child => {
                if (child.position < originalPosition) {
                    return;
                }

                child.position -= 1;
            });

            if (this.condition[this.config.childName].length === 1) {
                this.onDeleteAll();
                return;
            }

            condition.delete();
            this.condition[this.config.childName].splice(this.condition[this.config.childName].indexOf(condition), 1);

            if (this.condition[this.config.childName].length <= 0) {
                this.$nextTick(() => {
                    if (this.level === 0) {
                        this.onAddChildClick();
                    } else {
                        this.createCondition(this.config.placeholder, this.nextPosition);
                    }
                });
            }
        }
    }
};

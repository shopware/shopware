import './sw-meteor-page-context-item.scss';

const PRIORITIES = Object.freeze({
    ALWAYS: 'always',
    AUTO: 'auto',
    NEVER: 'never'
});

const { Component } = Shopware;

/**
 * @private
 */
Component.register('sw-meteor-page-context-item', {
    inheritAttrs: false,

    inject: [
        'registerAtParent',
        'removeFromParent',
        'closeSubMenu',
        'showSubMenu'
    ],

    mixins: [Shopware.Mixin.getByName('contextNodeMixin')],

    props: {
        value: {
            required: false,
            default: null
        },
        label: {
            type: String,
            required: true
        },
        icon: {
            type: String,
            required: false,
            default: null
        },
        event: {
            type: String,
            required: false,
            default: 'click'
        },
        priority: {
            type: String,
            required: false,
            default: 'never',

            validator(val) {
                return [PRIORITIES.ALWAYS, PRIORITIES.AUTO, PRIORITIES.NEVER].includes(val);
            }
        }
    },

    data() {
        return {
            parentNode: null
        };
    },

    computed: {
        depth() {
            return this.parentNode.depth + 1;
        },

        collapsed() {
            if (this.priority === PRIORITIES.ALWAYS) {
                return false;
            }

            if (this.priority === PRIORITIES.NEVER) {
                return true;
            }

            return this.parentNode.collapsed;
        },

        renderedChildren() {
            return this.childNodes.filter((child) => child.collapsed === false);
        },

        collapsedChildren() {
            return this.childNodes.filter((child) => child.collapsed);
        },

        hasCollapsedChildren() {
            return this.collapsedChildren.length > 0;
        },

        childCount() {
            return this.childNodes.length;
        },

        hasChildren() {
            return this.childCount > 0;
        }
    },

    created() {
        this.parentNode = this.registerAtParent(this);
    },

    mounted() {
        this.$el.remove();
    },

    beforeDestroy() {
        this.removeFromParent(this);
    },

    methods: {
        onElementClick() {
            if (!this.hasChildren) {
                this.$emit(this.event, this.value);
                this.closeSubMenu();
                return;
            }

            this.showSubMenu(this, this);
        },

        showParentSubMenu() {
            this.showSubMenu(this.parentNode, this);
        }
    },

    render(h) {
        if (typeof this.$scopedSlots.default === 'function') {
            return h('div', this.$scopedSlots.default());
        }

        return null;
    }
});

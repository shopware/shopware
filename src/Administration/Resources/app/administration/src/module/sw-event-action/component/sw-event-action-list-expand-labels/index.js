import template from './sw-event-action-list-expand-labels.html.twig';
import './sw-event-action-list-expand-labels.scss';

/**
 * @deprecated tag:v6.5.0 - Will be removed in v6.5.0. Please use `sw-flow` - Flow builder instead.
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Shopware.Component.register('sw-event-action-list-expand-labels', {
    template,

    props: {
        items: {
            type: Array,
            required: true,
        },
        increaseBy: {
            type: Number,
            required: false,
            default: 3,
        },
        defaultLimit: {
            type: Number,
            required: false,
            default: 2,
        },
        disabled: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    data() {
        return {
            limit: this.defaultLimit,
        };
    },

    computed: {
        classes() {
            return {
                'is--disabled': this.disabled,
            };
        },

        limitedItems() {
            return this.items.slice(0, this.limit);
        },

        remainingItemsAmount() {
            return this.items.length - this.limitedItems.length;
        },
    },

    methods: {
        increaseLimit() {
            if (this.disabled) {
                return;
            }
            this.limit += this.increaseBy;
        },
    },
});

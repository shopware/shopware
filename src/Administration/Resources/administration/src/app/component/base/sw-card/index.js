import template from './sw-card.html.twig';
import './sw-card.scss';

/**
 * @public
 * @description A card is a flexible and extensible content container.
 * @status ready
 * @example-type dynamic
 * @component-example
 * <sw-card title="Test title">
 *     Your content
 * </sw-card>
 */
export default {
    name: 'sw-card',
    template,

    props: {
        title: {
            type: String,
            required: false
        },
        hero: {
            type: Boolean,
            required: false,
            default: false
        },
        isLoading: {
            type: Boolean,
            required: false,
            default: false
        },
        large: {
            type: Boolean,
            required: false,
            default: false
        }
    },

    computed: {
        cardClasses() {
            return {
                'sw-card--tabs': !!this.$slots.tabs,
                'sw-card--grid': !!this.$slots.grid,
                'sw-card--hero': !!this.hero,
                'sw-card--large': this.large,
                'has--header': !!this.$slots.toolbar
            };
        }
    }
};

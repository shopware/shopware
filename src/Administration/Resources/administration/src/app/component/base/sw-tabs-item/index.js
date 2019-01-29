import template from './sw-tabs-item.html.twig';
import './sw-tabs-item.scss';

/**
 * @private
 */
export default {
    name: 'sw-tabs-item',
    template,

    props: {
        route: {
            type: [Object, String],
            required: false,
            default: ''
        },
        variant: {
            type: String,
            required: false,
            default: 'default',
            validValues: ['default', 'minimal'],
            validator(value) {
                if (!value.length) {
                    return true;
                }
                return ['default', 'minimal'].includes(value);
            }
        },
        name: {
            type: String,
            required: false,
            default: ''
        },
        active: {
            type: String,
            required: false,
            default: ''
        }
    },

    computed: {
        tabsItemClasses() {
            return {
                [`sw-tabs-item__${this.variant}`]: this.variant,
                'is--active': this.name !== '' && this.name === this.active
            };
        }
    },

    methods: {
        activate() {
            this.$parent.active = this.name;
        }
    }
};

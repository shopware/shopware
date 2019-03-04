import { Mixin } from 'src/core/shopware';
import template from './sw-attribute-set-renderer.html.twig';
import './sw-attribute-set-renderer.scss';

/**
 * @public
 * @status ready
 * @description
 * Renders attribute sets
 * @example-type code-only
 * @component-example
 */
export default {
    name: 'sw-attribute-set-renderer',
    template,

    mixins: [
        Mixin.getByName('sw-inline-snippet')
    ],

    // Grant access to some variables to the child form render components
    provide() {
        return {
            getEntity: this.entity,
            getAttributeSet: this.set,
            getAttributeSetVariant: this.variant
        };
    },

    props: {
        sets: {
            type: Array,
            required: true
        },
        entity: {
            type: Object,
            required: true
        },
        variant: {
            type: String,
            required: false,
            default: 'tabs',
            validValues: ['tabs', 'collapse'],
            validator(value) {
                if (!value.length) {
                    return true;
                }
                return ['tabs', 'collapse'].includes(value);
            }
        }
    },

    watch: {
        'entity.attributes': {
            handler() {
                this.initializeAttributes();
            }
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.initializeAttributes();
        },
        initializeAttributes() {
            if (!this.entity.attributes) {
                this.entity.attributes = {};
            }
        }
    }
};

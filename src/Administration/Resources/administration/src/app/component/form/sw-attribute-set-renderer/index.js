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

    props: {
        sets: {
            type: Array,
            required: true
        },
        entity: {
            type: Object,
            required: true
        }
    },

    data() {
        return {
            test: {}
        };
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

/**
 * @package buyers-experience
 */
import template from './sw-cms-section-actions.html.twig';
import './sw-cms-section-actions.scss';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    props: {
        section: {
            type: Object,
            required: true,
        },

        disabled: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    inject: ['feature'],

    data() {
        return {
            cmsPageState: Shopware.State.get('cmsPageState'),
        };
    },

    computed: {
        componentClasses() {
            return {
                'is--disabled': this.disabled,
            };
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            if (this.cmsPageState.selectedSection) {
                this.$store.dispatch('cmsPageState/setSection', this.section);
            }
        },

        selectSection() {
            if (this.disabled) {
                return;
            }

            this.$store.dispatch('cmsPageState/setSection', this.section);

            this.$parent.$parent.$emit('page-config-open', 'itemConfig');
        },
    },
};

import template from './sw-cms-section-actions.html.twig';
import './sw-cms-section-actions.scss';

const { Component } = Shopware;

Component.register('sw-cms-section-actions', {
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

    methods: {
        selectSection() {
            if (this.disabled) {
                return;
            }

            this.$store.dispatch('cmsPageState/setSection', this.section);
            this.$parent.$emit('page-config-open', 'itemConfig');
        },
    },
});

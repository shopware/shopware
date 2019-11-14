import template from './sw-cms-section-actions.html.twig';
import './sw-cms-section-actions.scss';

const { Component } = Shopware;

Component.register('sw-cms-section-actions', {
    template,

    props: {
        section: {
            type: Object,
            required: true
        }
    },

    data() {
        return {
            cmsPageState: Shopware.State.get('cmsPageState')
        };
    },

    methods: {
        selectSection() {
            this.$store.dispatch('cmsPageState/setSection', this.section);
            this.$parent.$emit('page-config-open', 'itemConfig');
        }
    }
});

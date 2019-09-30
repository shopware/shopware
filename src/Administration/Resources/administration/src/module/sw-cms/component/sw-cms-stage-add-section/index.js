import template from './sw-cms-stage-add-section.html.twig';
import './sw-cms-stage-add-section.scss';

const { Component } = Shopware;

Component.register('sw-cms-stage-add-section', {
    template,

    props: {
        forceChoose: {
            type: Boolean,
            required: false,
            default: false
        }
    },

    data() {
        return {
            showSelection: this.forceChoose
        };
    },

    methods: {
        onAddSection(type) {
            this.$emit('stage-section-add', type);
            this.showSelection = false;
        }
    }
});

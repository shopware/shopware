import template from './sw-cms-stage-section-selection.html.twig';
import './sw-cms-stage-section-selection.scss';

const { Component } = Shopware;

Component.register('sw-cms-stage-section-selection', {
    template,

    methods: {
        selectSection(type) {
            this.$emit('section-select', type);
        },
    },
});

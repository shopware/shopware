import template from './sw-cms-block-layout-config-margin-field.html.twig';
import './sw-cms-block-layout-config-margin-field.scss';

const { Component } = Shopware;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Component.register('sw-cms-block-layout-config-margin-field', {
    template,

    props: {
        value: {
            type: Object,
            required: true,
        },
    },

    computed: {
        sizes() {
            return [
                'xs',
                'sm',
                'md',
                'lg',
                'xl',
            ];
        }
    }
});

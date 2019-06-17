import { Component } from 'src/core/shopware';
import template from './sw-product-detail-base.html.twig';

Component.override('sw-product-detail-base', {
    template,

    methods: {
        createdComponent() {
            this.$super.createdComponent();
        }
    }
});

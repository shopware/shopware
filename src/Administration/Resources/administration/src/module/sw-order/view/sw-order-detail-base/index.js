import { Component } from 'src/core/shopware';
import template from './sw-order-detail-base.html.twig';
import './sw-order-detail-base.less';

Component.register('sw-order-detail-base', {
    template,

    props: {
        order: {
            type: Object,
            required: true,
            default: {}
        },
        isLoading: {
            type: Boolean,
            required: true,
            default: false
        },
        isLoaded: {
            type: Boolean,
            required: false,
            default: false
        }
    },

    computed: {
        moduleColor() {
            return this.$route.meta.$module.color;
        }
    }
});

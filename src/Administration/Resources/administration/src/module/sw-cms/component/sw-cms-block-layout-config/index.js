import { Component } from 'src/core/shopware';
import template from './sw-cms-block-layout-config.html.twig';
import './sw-cms-block-layout-config.scss';

Component.register('sw-cms-block-layout-config', {
    template,

    inject: ['cmsService'],

    model: {
        prop: 'block',
        event: 'block-update'
    },

    props: {
        block: {
            type: Object,
            required: true,
            default() {
                return {};
            }
        }
    },

    watch: {
        block: {
            deep: true,
            handler() {
                this.$emit('block-update', this.block);
            }
        }
    }
});

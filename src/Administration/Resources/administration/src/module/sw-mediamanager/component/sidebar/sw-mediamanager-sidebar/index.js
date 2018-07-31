import { Component } from 'src/core/shopware';
import template from './sw-mediamanager-sidebar.html.twig';
import './sw-mediamanager-sidebar.less';
import '../sw-mediamanager-quickinfo';

Component.register('sw-mediamanager-sidebar', {
    template,

    props: {
        item: {
            required: false,
            type: [Object],
            validator(value) {
                return value.type === 'media';
            }
        }
    },

    methods: {
        emitRequestMoveSelection(originalDomEvent) {
            this.$emit('sw-mediamanager-sidebar-move-batch', { originalDomEvent });
        },
        emitRequestRemoveSelection(originalDomEvent) {
            this.$emit('sw-mediamanager-sidebar-remove-batch', { originalDomEvent });
        }
    }
});

import { Component } from 'src/core/shopware';
import template from './sw-media-sidebar.html.twig';
import './sw-media-sidebar.less';
import '../sw-media-quickinfo';

Component.register('sw-media-sidebar', {
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

    data() {
        return {
            autoplay: false
        };
    },

    computed: {
        getKey() {
            let key = '';
            if (this.item) {
                key = this.item.id;
            }
            return key + this.autoplay;
        },

        hasItem() {
            return this.item !== null;
        }
    },

    methods: {
        emitRequestMoveSelection(originalDomEvent) {
            this.$emit('sw-media-sidebar-move-batch', { originalDomEvent });
        },

        emitRequestRemoveSelection(originalDomEvent) {
            this.$emit('sw-media-sidebar-remove-batch', { originalDomEvent });
        },

        showQuickInfo() {
            this.$refs.quickInfoButton.toggleContentPanel(true);
        }
    }
});

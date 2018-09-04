import { Component } from 'src/core/shopware';
import template from './sw-media-sidebar.html.twig';
import './sw-media-sidebar.less';
import '../sw-media-quickinfo';
import '../sw-media-quickinfo-multiple';

Component.register('sw-media-sidebar', {
    template,

    props: {
        items: {
            required: false,
            type: [Array],
            validator(value) {
                const invalidElements = value.filter((element) => {
                    return element.type !== 'media';
                });
                return invalidElements.length === 0;
            }
        }
    },

    watch: {
        item(value) {
            if (value === undefined || value === null) {
                this.$refs.quickInfoButton.toggleContentPanel(false);
            }
        }
    },

    data() {
        return {
            autoplay: false
        };
    },

    computed: {
        isSingleFile() {
            return this.items != null && this.items.length === 1;
        },

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

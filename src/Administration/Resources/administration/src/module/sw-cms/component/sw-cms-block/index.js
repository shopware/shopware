import { Component } from 'src/core/shopware';
import template from './sw-cms-block.html.twig';
import './sw-cms-block.scss';

Component.register('sw-cms-block', {
    template,

    inject: ['cmsService'],

    props: {
        block: {
            type: Object,
            required: true,
            default() {
                return {};
            }
        },
        active: {
            type: Boolean,
            required: false,
            default: false
        }
    },

    data() {
        return {
            backgroundUrl: null
        };
    },

    computed: {
        cmsBlocks() {
            return this.cmsService.getCmsBlockRegistry();
        },

        blockConfig() {
            return this.cmsBlocks[this.block.type];
        },

        blockClasses() {
            return {
                'is--boxed': this.block.sizingMode === 'boxed'
            };
        },

        blockStyles() {
            return {
                'background-color': this.block.backgroundColor || 'transparent',
                'background-image': this.backgroundUrl,
                'background-size': this.block.backgroundMediaMode
            };
        },

        blockPadding() {
            return {
                'padding-top': this.block.marginTop || '0px',
                'padding-bottom': this.block.marginBottom || '0px',
                'padding-left': this.block.marginLeft || '0px',
                'padding-right': this.block.marginRight || '0px'
            };
        },

        overlayClasses() {
            return {
                'is--active': this.active
            };
        },

        toolbarClasses() {
            return {
                'is--active': this.active
            };
        }
    },

    watch: {
        'block.backgroundMedia': {
            handler() {
                this.getBackgroundMedia();
            }
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            if (!this.block.backgroundMediaMode) {
                this.block.backgroundMediaMode = 'cover';
            }

            this.getBackgroundMedia();
        },

        onBlockOverlayClick() {
            this.$emit('onBlockOverlayClick');
        },

        onBlockDelete() {
            this.$emit('onBlockDelete');
        },

        onBlockDuplicate() {
            this.$emit('onBlockDuplicate');
        },

        getBackgroundMedia() {
            if (!this.block.backgroundMedia) {
                this.backgroundUrl = null;
                return;
            }

            this.backgroundUrl = `url(${this.block.backgroundMedia.url})`;
        }
    }
});

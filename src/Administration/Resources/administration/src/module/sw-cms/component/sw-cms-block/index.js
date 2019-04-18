import { Component, Entity } from 'src/core/shopware';
import template from './sw-cms-block.html.twig';
import './sw-cms-block.scss';

Component.register('sw-cms-block', {
    template,

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
        return {};
    },

    computed: {
        blockClasses() {
            return {
                'is--boxed': this.block.config.sizingMode === 'boxed'
            };
        },

        blockStyles() {
            return {
                'background-color': this.block.config.backgroundColor || 'transparent',
                'padding-top': this.block.config.marginTop || '0px',
                'padding-bottom': this.block.config.marginBottom || '0px',
                'padding-left': this.block.config.marginLeft || '0px',
                'padding-right': this.block.config.marginRight || '0px'
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

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            if (!this.block.config || this.block.config === null) {
                const blockSchema = Entity.getDefinition('cms_block');
                this.block.config = Entity.getRawEntityObject(blockSchema.properties.config);

                this.block.config.sizingMode = 'boxed';

                if (!this.block.config.name) {
                    this.block.config.name = null;
                }
            }
        },

        onBlockOverlayClick() {
            this.$emit('onBlockOverlayClick');
        },

        onBlockDelete() {
            this.$emit('onBlockDelete');
        },

        onBlockDuplicate() {
            this.$emit('onBlockDuplicate');
        }
    }
});

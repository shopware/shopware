import template from './sw-cms-block.html.twig';
import './sw-cms-block.scss';

const { Component, Filter } = Shopware;

Component.register('sw-cms-block', {
    template,

    inject: ['cmsService'],

    props: {
        block: {
            type: Object,
            required: true,
            default() {
                return {};
            },
        },

        active: {
            type: Boolean,
            required: false,
            default: false,
        },

        disabled: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    data() {
        return {
            backgroundUrl: null,
        };
    },

    computed: {
        customBlockClass() {
            return this.block.cssClass;
        },

        blockStyles() {
            let backgroundMedia = null;

            if (this.block.backgroundMedia) {
                if (this.block.backgroundMedia.id) {
                    backgroundMedia = `url("${this.block.backgroundMedia.url}")`;
                } else {
                    backgroundMedia = `url('${this.assetFilter(this.block.backgroundMedia.url)}')`;
                }
            }

            return {
                'background-color': this.block.backgroundColor || 'transparent',
                'background-image': backgroundMedia,
                'background-size': this.block.backgroundMediaMode,
            };
        },

        blockPadding() {
            return {
                'padding-top': this.block.marginTop || '0px',
                'padding-bottom': this.block.marginBottom || '0px',
                'padding-left': this.block.marginLeft || '0px',
                'padding-right': this.block.marginRight || '0px',
            };
        },

        overlayClasses() {
            return {
                'is--active': this.active,
            };
        },

        toolbarClasses() {
            return {
                'is--active': this.active,
            };
        },

        assetFilter() {
            return Filter.getByName('asset');
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            if (!this.block.backgroundMediaMode) {
                this.block.backgroundMediaMode = 'cover';
            }
        },

        onBlockOverlayClick() {
            if (!this.block.locked) {
                this.$emit('block-overlay-click');
            }
        },
    },
});

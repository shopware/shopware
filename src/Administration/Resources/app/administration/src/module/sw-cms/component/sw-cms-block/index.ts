import { type PropType } from 'vue';
import template from './sw-cms-block.html.twig';
import './sw-cms-block.scss';
import type CmsVisibility from '../../shared/CmsVisibility';

const { Filter, Store } = Shopware;

/**
 * @package buyers-experience
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default Shopware.Component.wrapComponentConfig({
    template,

    compatConfig: Shopware.compatConfig,

    emits: ['block-overlay-click'],

    props: {
        block: {
            type: Object as PropType<EntitySchema.Entity<'cms_block'>>,
            required: true,
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

        hasWarnings: {
            type: Boolean,
            required: false,
            default: false,
        },

        hasErrors: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    data() {
        return {
            backgroundUrl: null as string | null,
            isCollapsed: true,
        };
    },

    computed: {
        customBlockClass() {
            const errorCssClasses = {
                'has--warning': !this.hasErrors && this.hasWarnings,
                'has--error': this.hasErrors,
            };

            if (!this.block.cssClass) {
                return errorCssClasses;
            }

            return this.block?.cssClass?.split(' ').reduce((accumulator: { [ key: string]: boolean }, className) => {
                accumulator[className] = true;

                return accumulator;
            }, errorCssClasses);
        },

        blockStyles() {
            let backgroundMedia = null;

            if (this.block.backgroundMedia) {
                const url = this.block.backgroundMedia.url!;

                if (this.block.backgroundMedia.id) {
                    backgroundMedia = `url("${url}")`;
                } else {
                    backgroundMedia = `url("${this.assetFilter(url)}")`;
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

        isVisible() {
            const view = Store.get('cmsPage').currentCmsDeviceView;

            const visibility = this.block.visibility as CmsVisibility;

            return (view === 'desktop' && !visibility.desktop) ||
                (view === 'tablet-landscape' && !visibility.tablet) ||
                (view === 'mobile' && !visibility.mobile);
        },

        toggleButtonText() {
            return this.$tc('sw-cms.sidebar.contentMenu.visibilityBlockTextButton', this.isCollapsed ? 0 : 1);
        },

        expandedClass() {
            return {
                'is--expanded': this.isVisible && !this.isCollapsed,
            };
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

        toggleVisibility() {
            this.isCollapsed = !this.isCollapsed;
        },
    },
});

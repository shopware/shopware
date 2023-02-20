import template from './sw-cms-block.html.twig';
import './sw-cms-block.scss';

/**
 * @package content
 */

const { Filter, State } = Shopware;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
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
            backgroundUrl: null,
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

            return this.block?.cssClass?.split(' ').reduce((accumulator, className) => {
                accumulator[className] = true;

                return accumulator;
            }, errorCssClasses);
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

        isVisible() {
            const view = State.get('cmsPageState').currentCmsDeviceView;

            return (view === 'desktop' && !this.block.visibility.desktop) ||
                (view === 'tablet-landscape' && !this.block.visibility.tablet) ||
                (view === 'mobile' && !this.block.visibility.mobile);
        },

        toggleButtonText() {
            return this.$tc('sw-cms.sidebar.contentMenu.visibilityBlockTextButton', !this.isCollapsed);
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
};

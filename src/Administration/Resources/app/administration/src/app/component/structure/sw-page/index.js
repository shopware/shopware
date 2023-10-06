import template from './sw-page.html.twig';
import './sw-page.scss';

const { Component } = Shopware;
const { dom } = Shopware.Utils;

/**
 * @package admin
 *
 * @deprecated tag:v6.6.0 - Will be private
 * @public
 * @description
 * Container for the content of a page, including the search bar, page header, actions and the actual content.
 * @status ready
 * @example-type static
 * @component-example
 * <sw-page style="height: 550px; border: 1px solid #D8DDE6;">
 *     <template #search-bar>
 *         <sw-search-bar>
 *         </sw-search-bar>
 *     </template>
 *     <template #smart-bar-header>
 *         <h2>Lorem ipsum page</h2>
 *     </template>
 *     <template #smart-bar-actions>
 *         <sw-button variant="primary">
 *             Action
 *         </sw-button>
 *     </template>
 *     <template #side-content>
 *         <sw-card-view>
 *             <sw-card>
 *                 Lorem ipsum dolor sit amet, consetetur sadipscing elitr
 *             </sw-card>
 *         </sw-card-view>
 *     </template>
 *     <template #content>
 *         <sw-card-view>
 *             <sw-card title="Card1" large></sw-card>
 *             <sw-card title="Card2" large></sw-card>
 *         </sw-card-view>
 *     </template>
 * </sw-page>
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Component.register('sw-page', {
    template,

    props: {
        /**
         * Toggles smart bar
         */
        showSmartBar: {
            type: Boolean,
            // TODO: Boolean props should only be opt in and therefore default to false
            // eslint-disable-next-line vue/no-boolean-default
            default: true,
        },
        /**
         * Toggles search bar
         */
        showSearchBar: {
            type: Boolean,
            // TODO: Boolean props should only be opt in and therefore default to false
            // eslint-disable-next-line vue/no-boolean-default
            default: true,
        },
        /**
         * Overrides the module color as the bottom-border-color of the page's smart bar
         */
        headerBorderColor: {
            type: String,
            required: false,
            default: '',
        },
    },

    data() {
        return {
            module: null,
            parentRoute: null,
            sidebarOffset: 0,
            scrollbarOffset: 0,
            hasFullWidthHeader: false,
            languageId: '',
        };
    },

    computed: {
        pageColor() {
            if (this.headerBorderColor) {
                return this.headerBorderColor;
            }

            if (this.module?.color) {
                return this.module.color;
            }

            return '#d8dde6';
        },

        hasSideContentSlot() {
            return !!this.$slots['side-content'];
        },

        hasSidebarSlot() {
            return !!this.$slots.sidebar;
        },

        showHeadArea() {
            return this.showSearchBar || this.showSmartBar;
        },

        pageClasses() {
            return {
                'has--head-area': this.showHeadArea,
            };
        },

        pageContainerClasses() {
            return {
                'has--smart-bar': this.showSmartBar,
            };
        },

        pageContentClasses() {
            return {
                'has--smart-bar': !!this.showSmartBar,
                'has--side-content': !!this.hasSideContentSlot,
                'has--side-bar ': !!this.hasSidebarSlot && !this.hasSideContentSlot,
            };
        },

        pageOffset() {
            if (this.hasFullWidthHeader) {
                return 0;
            }
            return `${this.sidebarOffset + this.scrollbarOffset}px`;
        },

        headerStyles() {
            return {
                'border-bottom-color': this.pageColor,
                'padding-right': this.pageOffset,
            };
        },

        topBarActionStyles() {
            return {
                'margin-right': `-${this.pageOffset}`,
            };
        },

        additionalEventListeners() {
            return this.$listeners;
        },

        smartBarContentStyle() {
            const rowNumber = this.showSearchBar ? 2 : 1;

            return {
                'grid-row': rowNumber,
            };
        },
    },

    created() {
        this.createdComponent();
    },

    mounted() {
        this.mountedComponent();
    },

    updated() {
        this.updatedComponent();
    },

    beforeDestroy() {
        Shopware.State.dispatch('error/resetApiErrors');
        this.beforeDestroyComponent();
    },

    methods: {
        createdComponent() {
            this.$on('mount', this.setSidebarOffset);
            this.$on('destroy', this.removeSidebarOffset);
            window.addEventListener('resize', this.readScreenWidth);
        },

        mountedComponent() {
            this.initPage();
            this.readScreenWidth();
            this.setScrollbarOffset();
        },

        updatedComponent() {
            this.setScrollbarOffset();
        },

        beforeDestroyComponent() {
            window.removeEventListener('resize', this.readScreenWidth);
        },

        readScreenWidth() {
            this.hasFullWidthHeader = document.body.clientWidth <= 500;
        },

        setSidebarOffset(sidebarWidth) {
            this.sidebarOffset = sidebarWidth;
        },

        removeSidebarOffset() {
            this.sidebarOffset = 0;
        },

        setScrollbarOffset() {
            let contentEl = document.querySelector('.sw-card-view__content');

            if (!contentEl) {
                contentEl = document.querySelector('.sw-page__main-content-inner');
            }

            if (contentEl !== null) {
                this.scrollbarOffset = dom.getScrollbarWidth(contentEl);
            }
        },

        initPage() {
            if (this.$route.meta.$module) {
                this.module = this.$route.meta.$module;
            }

            if (this.$route.meta.parentPath) {
                this.parentRoute = this.$route.meta.parentPath;
            }
        },
    },
});

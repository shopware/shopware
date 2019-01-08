import { State } from 'src/core/shopware';
import dom from 'src/core/service/utils/dom.utils';
import template from './sw-page.html.twig';
import './sw-page.less';

/**
 * @public
 * @description
 * Container for the content of a page, including the search bar, page header, actions and the actual content.
 * @status ready
 * @example-type static
 * @component-example
 * <sw-page style="height: 550px; border: 1px solid #D8DDE6;">
 *     <template slot="search-bar">
 *         <sw-search-bar>
 *         </sw-search-bar>
 *     </template>
 *     <template slot="smart-bar-header">
 *         <h2>
 *             Lorem ipsum page
 *         </h2>
 *     </template>
 *     <template slot="smart-bar-actions">
 *         <sw-button variant="primary">
 *             Action
 *         </sw-button>
 *     </template>
 *     <template slot="content">
 *         <sw-card-view>
 *             <sw-card title="Card1" large></sw-card>
 *             <sw-card title="Card2" large></sw-card>
 *         </sw-card-view>
 *     </template>
 * </sw-page>
 */
export default {
    name: 'sw-page',
    template,

    props: {
        showSmartBar: {
            type: Boolean,
            default: true
        }
    },

    data() {
        return {
            module: null,
            parentRoute: null,
            sidebarOffset: 0,
            scrollbarOffset: 0,
            hasFullWidthHeader: false,
            languageId: ''
        };
    },

    computed: {
        languageStore() {
            return State.getStore('language');
        },

        pageColor() {
            return (this.module !== null) ? this.module.color : '#d8dde6';
        },

        hasAdditionalSlot() {
            return !!this.$slots.additional;
        },

        pageContainerClasses() {
            return {
                'has--smart-bar': this.showSmartBar
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
                'padding-right': this.pageOffset
            };
        }
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
        this.beforeDestroyComponent();
    },

    methods: {
        createdComponent() {
            this.$on('sw-sidebar-mounted', this.setSidebarOffset);
            this.$on('sw-sidebar-destroyed', this.removeSidebarOffset);
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
            const contentEl = document.querySelector('.sw-card-view__content');

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
        }
    }
};

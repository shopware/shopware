import { Component } from 'src/core/shopware';
import dom from 'src/core/service/utils/dom.utils';
import template from './sw-page.html.twig';
import './sw-page.less';

Component.register('sw-page', {
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
            scrollbarOffset: 0
        };
    },

    computed: {
        pageColor() {
            return (this.module !== null) ? this.module.color : '#d8dde6';
        },

        pageContainerClasses() {
            return {
                'has--smart-bar': this.showSmartBar
            };
        },

        pageOffset() {
            return `${this.sidebarOffset + this.scrollbarOffset}px`;
        },

        smartBarStyles() {
            return {
                'border-bottom-color': this.pageColor,
                'padding-right': this.pageOffset
            };
        },

        searchBarStyles() {
            return {
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

    methods: {
        createdComponent() {
            this.$on('sw-sidebar-mounted', this.setSidebarOffset);
            this.$on('sw-sidebar-destroyed', this.removeSidebarOffset);
        },

        mountedComponent() {
            this.initPage();
            this.setScrollbarOffset();
        },

        updatedComponent() {
            this.setScrollbarOffset();
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
});

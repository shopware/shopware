import { Component } from 'src/core/shopware';
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

        smartBarStyles() {
            return {
                'border-bottom-color': this.pageColor,
                'padding-right': `${this.scrollbarOffset}px`
            };
        }
    },

    mounted() {
        this.initPage();
    },

    updated() {
        this.setScrollbarOffset();
    },

    methods: {
        initPage() {
            if (this.$route.meta.$module) {
                this.module = this.$route.meta.$module;
            }

            if (this.$route.meta.parentPath) {
                this.parentRoute = this.$route.meta.parentPath;
            }
        },

        setScrollbarOffset() {
            this.scrollbarOffset = dom.getScrollbarWidth(this.$refs.swPageContent.firstChild);
        }
    }
});

/**
 * @package system-settings
 */
import template from './sw-settings-snippet-sidebar.html.twig';
import './sw-settings-snippet-sidebar.scss';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    props: {
        filterItems: {
            type: Array,
            required: true,
        },

        authorFilters: {
            type: Array,
            required: true,
        },

        filterSettings: {
            type: Object,
            required: false,
            default: null,
        },
    },

    computed: {
        activeFilterNumber() {
            let count = 0;

            if (!this.filterSettings) {
                return count;
            }

            Object.values(this.filterSettings).forEach((value) => {
                if (value === true) {
                    count += 1;
                }
            });

            return count;
        },

        isExpandedAuthorFilters() {
            if (!this.filterSettings) {
                return false;
            }

            return this.authorFilters.some((item) => this.filterSettings[item] === true);
        },

        isExpandedMoreFilters() {
            if (!this.filterSettings) {
                return false;
            }

            return this.filterItems.some((item) => this.filterSettings[item] === true);
        },
    },

    methods: {
        closeContent() {
            if (this.filterSidebarIsOpen) {
                this.$refs.filterSideBar.closeContent();
                this.filterSidebarIsOpen = false;
                this.$emit('sw-sidebar-close');
                return;
            }

            this.$refs.filterSideBar.openContent();
            this.filterSidebarIsOpen = true;

            this.$emit('sw-sidebar-open');
        },

        onChange(field) {
            this.$emit('change', field);
        },

        onRefresh() {
            this.$emit('sw-sidebar-collaps-refresh-grid');
        },

        resetAll() {
            this.$emit('sidebar-reset-all');
        },
    },
};

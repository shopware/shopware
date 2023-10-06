import './sw-wizard.scss';
import template from './sw-wizard.html.twig';

const { Component } = Shopware;

/**
 * @package admin
 *
 * @deprecated tag:v6.6.0 - Will be private
 * @public
 * @description Provides a wrapper to create a wizard modal. The wizard pages are placed in the default slot of the
 * component. Dot navigation as well as the navigation buttons are dynamically within the wizard itself.
 * Please use `sw-wizard-page` for the different wizard pages. When a more sophisticated wizard page is necessary,
 * please extend the default `sw-wizard-page`.
 * @status ready
 * @example-type code-only
 * @component-example
 * <sw-wizard :showDotNavigation="true">
 *     <sw-wizard-page position="1" title="Example #1">
 *         <h1>Example #1</h1>
 *     </sw-wizard-page>
 *     <sw-wizard-page position="2" title="Example #2">
 *         <h1>Example #2</h1>
 *     </sw-wizard-page>
 *     <sw-wizard-page position="3" title="Example #3">
 *         <h1>Example #3</h1>
 *     </sw-wizard-page>
 * </sw-wizard>
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Component.register('sw-wizard', {
    template,

    props: {
        showNavigationDots: {
            type: Boolean,
            required: false,
            // TODO: Boolean props should only be opt in and therefore default to false
            // eslint-disable-next-line vue/no-boolean-default
            default() {
                return false;
            },
        },

        activePage: {
            type: Number,
            required: false,
            default() {
                return 0;
            },
        },

        leftButtonDisabled: {
            type: Boolean,
            required: false,
            // TODO: Boolean props should only be opt in and therefore default to false
            // eslint-disable-next-line vue/no-boolean-default
            default() {
                return false;
            },
        },

        rightButtonDisabled: {
            type: Boolean,
            required: false,
            // TODO: Boolean props should only be opt in and therefore default to false
            // eslint-disable-next-line vue/no-boolean-default
            default() {
                return false;
            },
        },
    },

    data() {
        return {
            pages: [],
            currentlyActivePage: this.activePage,
            title: this.$attrs.title || '',
        };
    },

    computed: {
        hasFooterSlot() {
            return !!this.$slots['footer-left-button']
                || !!this.$slots['footer-right-button'];
        },

        pagesCount() {
            return this.pages.length;
        },
    },

    mounted() {
        this.mountedComponent();
    },

    methods: {
        mountedComponent() {
            this.changePage(this.currentlyActivePage);
        },

        addPage(component) {
            this.pages.push(component);
            this.$emit('pages-updated', this.pages, component, 'add');
        },

        removePage(component) {
            this.pages = this.pages.filter((page) => {
                return page !== component;
            });

            this.$emit('pages-updated', this.pages, component, 'remove');
        },

        nextPage() {
            const newPage = this.currentlyActivePage + 1;

            if (newPage > this.pagesCount) {
                return false;
            }

            this.changePage(newPage);
            return true;
        },

        previousPage() {
            const newPage = this.currentlyActivePage - 1;

            if (newPage < 0) {
                return false;
            }

            this.changePage(newPage);
            return true;
        },

        changePage(newPageIndex) {
            if (!this.pagesCount) {
                return;
            }

            this.currentlyActivePage = newPageIndex;
            this.pages.forEach((page) => {
                page.isCurrentlyActive = newPageIndex === page.position;
            });

            const page = this.pages.find((pageComponent) => {
                return pageComponent.position === newPageIndex;
            });

            // Set title of the modal
            if (page) {
                this.title = page.title || page.modalTitle;
            }

            this.$emit('current-page-change', this.currentlyActivePage, page);
        },

        onClose() {
            this.$emit('close');
        },
    },
});

import template from './sw-help-sidebar.html.twig';
import './sw-help-sidebar.scss';

/**
 * @description Displays the help sidebar
 *
 * @package buyers-experience
 *
 * @private
 */
export default Shopware.Component.wrapComponentConfig({
    template,

    inject: ['shortcutService'],

    props: {
        /**
         * @description The selector of the element where the sidebar should be appended to
         * @default body
         * @type {String}
         * @required false
         * @public
         * @example <sw-help-sidebar selector="body"></sw-help-sidebar>
        */
        selector: {
            type: String,
            required: false,
            default: 'body',
        },
    },

    computed: {
        showHelpSidebar(): boolean {
            return Shopware.State.get('adminHelpCenter').showHelpSidebar;
        },
    },

    created() {
        this.createdComponent();
    },

    mounted() {
        this.mountedComponent();
    },

    beforeUnmount() {
        this.beforeUnmountComponent();
    },

    unmounted() {
        this.unmountedComponent();
    },

    methods: {
        createdComponent(): void {
            // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access, @typescript-eslint/no-unsafe-call
            this.shortcutService.startEventListener();
        },

        /**
         * @returns {void}
         * @description Adds the sidebar to the DOM
         * @private
        */
        mountedComponent(): void {
            const el = document.querySelector(this.selector) as HTMLElement;

            if (!el) {
                return;
            }

            el.appendChild(this.$el);
            this.setFocusToSidebar();
        },

        /**
         * @returns {void}
         * @description Removes the sidebar from the DOM after the transition is finished
         * @private
        */
        beforeUnmountComponent(): void {
            const el = this.$el as HTMLElement;

            window.setTimeout(() => {
                el.remove();
            }, 800);
        },

        unmountedComponent(): void {
            // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access, @typescript-eslint/no-unsafe-call
            this.shortcutService.stopEventListener();
        },

        setFocusToSidebar(): void {
            const helpSidebarContainer = this.$refs.helpSidebarContainer as HTMLElement;

            if (!helpSidebarContainer) {
                return;
            }

            helpSidebarContainer.focus();
        },

        /**
         * @param {MouseEvent} event
         * @returns {void}
         * @description Closes the sidebar if the user clicks outside of the sidebar
         * @private
        */
        mouseDown(event: MouseEvent): void {
            const helpSidebarContainer = this.$refs.helpSidebarContainer as HTMLElement;

            if (!helpSidebarContainer) {
                return;
            }

            if (helpSidebarContainer.contains(event.target as Node)) {
                return;
            }

            this.closeHelpSidebar();
        },

        /**
         * @param {KeyboardEvent} event
         * @returns {void}
         * @description Closes the sidebar if the user presses the escape key
         * @private
        */
        escKey(event: KeyboardEvent): void {
            const target = event.target as HTMLElement;

            if (!target) {
                return;
            }

            if (!target.classList.contains('sw-help-sidebar__container')) {
                return;
            }

            if (target !== document.activeElement) {
                return;
            }

            if (event.key !== 'Escape') {
                return;
            }

            this.closeHelpSidebar();
        },

        closeHelpSidebar(): void {
            Shopware.State.commit('adminHelpCenter/setShowHelpSidebar', false);
        },

        openShortcutModal(): void {
            Shopware.State.commit('adminHelpCenter/setShowShortcutModal', true);
        },
    },
});

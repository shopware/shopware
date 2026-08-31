import { classifyPlatform, formatShortcutKey, type ShortcutKeyLabel } from 'src/core/helper/shortcut-key.helper';
import template from './sw-help-sidebar.html.twig';
import './sw-help-sidebar.scss';

const MOBILE_VIEWPORT_WIDTH = 500;

/**
 * @description Displays the help sidebar
 *
 * @sw-package framework
 *
 * @private
 *
 * @deprecated tag:v6.8.0 - Will be removed without replacement. The help center content
 * now lives directly in sw-help-center, rendered as an mt-popover instead of a sidebar.
 */
export default Shopware.Component.wrapComponentConfig({
    template,

    inject: ['shortcutService'],

    data(): {
        viewportWidth: number;
    } {
        return {
            viewportWidth: window.innerWidth,
        };
    },

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
        focusTrigger: {
            type: Number,
            required: false,
            default: 0,
        },
    },

    computed: {
        showHelpSidebar(): boolean {
            return Shopware.Store.get('adminHelpCenter').showHelpSidebar;
        },

        showShortcutButton(): boolean {
            return this.viewportWidth > MOBILE_VIEWPORT_WIDTH;
        },

        shortcutTooltipKeys(): ShortcutKeyLabel[] {
            const platform = classifyPlatform(window.navigator.platform);

            return this.$t('sw-shortcut-overview.keyboardShortcutSpecialShortcutShortcutListing')
                .split(' ')
                .flatMap((key: string) => key.split('-'))
                .filter(Boolean)
                .map((key: string) => formatShortcutKey(key, platform));
        },

        shortcutTooltipContent(): string {
            const title = `<b class="sw-help-sidebar__tooltip-title">${this.$t('sw-shortcut-overview.title')}</b>`;

            const keys = this.shortcutTooltipKeys
                .map(
                    (key) =>
                        `<b class="sw-help-sidebar__tooltip-shortcut-key" aria-label="${key.ariaLabel}">${key.label}</b>`,
                )
                .join(' ');

            return `${title} ${keys}`;
        },
    },

    watch: {
        $route(): void {
            this.closeHelpSidebar();
        },

        focusTrigger(): void {
            this.setFocusToSidebar();
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
            // eslint-disable-next-line @typescript-eslint/unbound-method
            window.addEventListener('resize', this.updateViewportWidth);

            const el = document.querySelector(this.selector) as HTMLElement;

            if (!el) {
                return;
            }

            el.appendChild(this.$el);
            this.setFocusToSidebar();
        },

        updateViewportWidth(): void {
            this.viewportWidth = window.innerWidth;
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
            // eslint-disable-next-line @typescript-eslint/unbound-method
            window.removeEventListener('resize', this.updateViewportWidth);

            // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access, @typescript-eslint/no-unsafe-call
            this.shortcutService.stopEventListener();
        },

        getHelpSidebarContainer(): HTMLElement | null {
            return (this.$refs.container as HTMLElement) ?? null;
        },

        setFocusToSidebar(): void {
            const helpSidebarContainer = this.getHelpSidebarContainer();

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
            const helpSidebarContainer = this.getHelpSidebarContainer();

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
            Shopware.Store.get('adminHelpCenter').showHelpSidebar = false;
        },

        openShortcutModal(): void {
            Shopware.Store.get('adminHelpCenter').showShortcutModal = true;
        },
    },
});

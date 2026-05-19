import template from './sw-help-center.html.twig';
import './sw-help-center.scss';

/**
 * @description Displays an icon and a link to the help sidebar
 *
 * @sw-package framework
 *
 * @private
 */
export default Shopware.Component.wrapComponentConfig({
    template,

    data(): {
        helpSidebarFocusTrigger: number;
    } {
        return {
            helpSidebarFocusTrigger: 0,
        };
    },

    computed: {
        showHelpSidebar(): boolean {
            return Shopware.Store.get('adminHelpCenter').showHelpSidebar;
        },

        showShortcutModal(): boolean {
            return Shopware.Store.get('adminHelpCenter').showShortcutModal;
        },
    },

    watch: {
        showShortcutModal(value) {
            if (value === true) {
                return;
            }

            if (!this.showHelpSidebar) {
                return;
            }

            this.helpSidebarFocusTrigger += 1;
        },
    },

    methods: {
        openHelpSidebar(): void {
            Shopware.Store.get('adminHelpCenter').showHelpSidebar = true;
        },

        openShortcutModal(): void {
            Shopware.Store.get('adminHelpCenter').showShortcutModal = true;
        },

        closeShortcutModal(): void {
            Shopware.Store.get('adminHelpCenter').showShortcutModal = false;
        },
    },
});

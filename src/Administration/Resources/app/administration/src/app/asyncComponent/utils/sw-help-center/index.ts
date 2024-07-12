import template from './sw-help-center.html.twig';
import './sw-help-center.scss';

/**
 * @description Displays an icon and a link to the help sidebar
 *
 * @package buyers-experience
 *
 * @private
 */
export default Shopware.Component.wrapComponentConfig({
    template,

    compatConfig: Shopware.compatConfig,

    computed: {
        showHelpSidebar(): boolean {
            return Shopware.State.get('adminHelpCenter').showHelpSidebar;
        },

        showShortcutModal(): boolean {
            return Shopware.State.get('adminHelpCenter').showShortcutModal;
        },
    },

    watch: {
        showShortcutModal(value) {
            const shortcutModal = this.$refs.shortcutModal as { onOpenShortcutOverviewModal: () => void };

            if (!shortcutModal) {
                return;
            }

            if (value === false) {
                this.setFocusToSidebar();

                return;
            }

            shortcutModal.onOpenShortcutOverviewModal();
        },
    },

    methods: {
        openHelpSidebar(): void {
            Shopware.State.commit('adminHelpCenter/setShowHelpSidebar', true);
        },

        openShortcutModal(): void {
            Shopware.State.commit('adminHelpCenter/setShowShortcutModal', true);
        },

        closeShortcutModal(): void {
            Shopware.State.commit('adminHelpCenter/setShowShortcutModal', false);
        },

        setFocusToSidebar(): void {
            const helpSidebar = this.$refs.helpSidebar as { setFocusToSidebar: () => void };

            if (!helpSidebar) {
                return;
            }

            helpSidebar.setFocusToSidebar();
        },
    },
});

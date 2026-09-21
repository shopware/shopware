import template from './sw-help-center.html.twig';
import './sw-help-center.scss';
import useAdminHelpCenterStore from 'shopware:stores/adminHelpCenter';

type ShortcutModal = {
    onOpenShortcutOverviewModal: () => void;
};

/**
 * @description Displays an icon and an action menu with the help center content
 *
 * @sw-package framework
 *
 * @private
 */
export default Shopware.Component.wrapComponentConfig({
    template,

    computed: {
        showHelpSidebar(): boolean {
            return useAdminHelpCenterStore().showHelpSidebar;
        },

        showShortcutModal(): boolean {
            return useAdminHelpCenterStore().showShortcutModal;
        },
    },

    watch: {
        showShortcutModal(value: boolean): void {
            if (!value) {
                return;
            }

            const shortcutModal = this.$refs.shortcutModal as ShortcutModal | undefined;

            shortcutModal?.onOpenShortcutOverviewModal();
        },
    },

    methods: {
        onVisibilityChange(isOpened: boolean): void {
            useAdminHelpCenterStore().showHelpSidebar = isOpened;
        },

        openShortcutModal(): void {
            useAdminHelpCenterStore().showShortcutModal = true;
        },

        closeShortcutModal(): void {
            useAdminHelpCenterStore().showShortcutModal = false;
        },
    },
});

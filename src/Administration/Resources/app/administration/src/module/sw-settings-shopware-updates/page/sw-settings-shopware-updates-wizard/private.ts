import { definePrivateComponent } from 'src/core/public-component';

/**
 * @sw-package framework
 *
 * The component is registered by its name for the Administration only: it never reaches extensions.
 * The lists hold everything it offered before it became a mini-module,
 * because Component.override inside the Administration could reach all of it. The files in `./src` are private to this module.
 */
/** @private */
export default definePrivateComponent({
    name: 'sw-settings-shopware-updates-wizard',
    load: () => import('./src/sw-settings-shopware-updates-wizard'),
    public: {
        emits: [
            'update-started',
            'update-stopped',
        ],
        blocks: [
            'sw_settings_shopware_updates_wizard',
            'sw_settings_shopware_updates_wizard_smart_bar_header',
            'sw_settings_shopware_updates_wizard_content',
            'sw_settings_shopware_updates_wizard_version_card',
            'sw_settings_shopware_updates_wizard_license_banner',
            'sw_settings_shopware_updates_wizard_check_failed',
            'sw_settings_shopware_updates_wizard_up_to_date',
            'sw_settings_shopware_updates_wizard_update_methods',
            'sw_settings_shopware_updates_wizard_methods_headline',
            'sw_settings_shopware_updates_wizard_cli_method',
            'sw_settings_shopware_updates_wizard_method_divider',
            'sw_settings_shopware_updates_wizard_web_installer',
            'sw_settings_shopware_updates_wizard_method_warnings',
            'sw_settings_shopware_updates_wizard_method_plugin_options',
        ],
        computed: [
            'updatePossible',
            'updateButtonTooltipMessage',
            'displayIncompatibleExtensionsWarning',
            'displayUnknownExtensionsWarning',
            'optionDeactivateIncompatibleTranslation',
            'optionDeactivateAllTranslation',
            'currentVersion',
            'isUpdateAvailable',
            'licenseCheckFailed',
            'updateStatusBadgeVariant',
            'updateStatusBadgeLabel',
            'changelogUrl',
            'cliUpgradeCommand',
            'cliInstallUrl',
        ],
        methods: [
            '_navigateTo',
            'copyCliCommand',
            'createdComponent',
            'startUpdateProcess',
            'stopUpdateProcess',
            'downloadRecovery',
            'deactivateExtensions',
            'buildRecoveryUrl',
            'redirectToPage',
        ],
    },
});

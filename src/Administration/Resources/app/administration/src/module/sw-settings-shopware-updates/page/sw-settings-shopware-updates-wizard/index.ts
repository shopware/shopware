import template from './sw-settings-shopware-updates-wizard.html.twig';
import './sw-settings-shopware-updates-wizard.scss';
import useSession from 'src/app/composables/use-session';
import useSnackbar from 'src/app/composables/use-snackbar';

const { Component, Mixin } = Shopware;

/**
 * @sw-package framework
 * @private
 */
export default Component.wrapComponentConfig({
    template,

    inject: ['updateService'],

    emits: [
        'update-started',
        'update-stopped',
    ],

    mixins: [
        Mixin.getByName('notification'),
    ],

    data(): {
        updateInfo: {
            version: unknown;
            changelog: unknown;
        };
        licenseValid: boolean;
        extensions: Array<{ statusName: string }>;
        isLoading: boolean;
        checkedBackupCheckbox: boolean;
        updateRunning: boolean;
        progressbarValue: number;
        step: string;
        updaterIsRunning: boolean;
        updateModalShown: boolean;
        chosenExtensionBehaviour: string;
        autoUpdateEnabled: boolean;
        clusterSetup: boolean;
    } {
        return {
            updateInfo: {
                version: null,
                changelog: null,
            },
            licenseValid: true,
            extensions: [],
            isLoading: true,
            checkedBackupCheckbox: false,
            updateRunning: false,
            progressbarValue: 0,
            step: 'download',
            updaterIsRunning: false,
            updateModalShown: false,
            chosenExtensionBehaviour: '',
            autoUpdateEnabled: true,
            clusterSetup: false,
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(),
        };
    },
    computed: {
        updatePossible() {
            return this.licenseValid && this.autoUpdateEnabled && !this.clusterSetup;
        },

        updateButtonTooltip() {
            if (this.updatePossible) {
                return {
                    message: '',
                    disabled: true,
                };
            }

            let message = 'sw-settings-shopware-updates.license.bannerTitle';

            if (this.clusterSetup) {
                message = 'sw-settings-shopware-updates.infos.clusterSetupDisabled';
            } else if (!this.autoUpdateEnabled) {
                message = 'sw-settings-shopware-updates.infos.autoUpdateDisabled';
            }

            return {
                message: this.$t(message),
                position: 'bottom',
            };
        },

        displayIncompatibleExtensionsWarning() {
            return this.extensions.some((extension) => {
                return extension.statusName !== 'compatible' && extension.statusName !== 'notInStore';
            });
        },

        displayUnknownExtensionsWarning() {
            return this.extensions.some((extension) => {
                return extension.statusName === 'notInStore';
            });
        },

        optionDeactivateIncompatibleTranslation() {
            const deactivateIncompatTrans = this.$t(
                'sw-settings-shopware-updates.extensions.actions.deactivateIncompatible',
            );
            const isRecommended =
                this.displayIncompatibleExtensionsWarning && !this.displayUnknownExtensionsWarning
                    ? this.$t('sw-settings-shopware-updates.extensions.actions.recommended')
                    : '';

            return `${deactivateIncompatTrans} ${isRecommended}`;
        },

        optionDeactivateAllTranslation() {
            const deactiveAllTrans = this.$t('sw-settings-shopware-updates.extensions.actions.deactivateAll');
            const isRecommended =
                this.displayIncompatibleExtensionsWarning && this.displayUnknownExtensionsWarning
                    ? this.$t('sw-settings-shopware-updates.extensions.actions.recommended')
                    : '';

            return `${deactiveAllTrans} ${isRecommended}`;
        },

        currentVersion(): string {
            return Shopware.Context.app.config.version ?? '';
        },

        isUpdateAvailable(): boolean {
            return Boolean(this.updateInfo.version);
        },

        licenseCheckFailed(): boolean {
            return !this.licenseValid;
        },

        updateStatusBadgeVariant(): 'attention' | 'positive' {
            return this.isUpdateAvailable ? 'attention' : 'positive';
        },

        updateStatusBadgeLabel(): string {
            return this.$t(
                this.isUpdateAvailable
                    ? 'sw-settings-shopware-updates.versionCard.badgeUpdateAvailable'
                    : 'sw-settings-shopware-updates.versionCard.badgeUpToDate',
            );
        },

        changelogUrl(): string {
            return `https://github.com/shopware/shopware/releases/`;
        },

        cliUpgradeCommand(): string {
            return 'shopware-cli project upgrade';
        },

        cliInstallUrl(): string {
            return 'https://developer.shopware.com/docs/products/tools/cli/';
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        /** Thin wrapper so tests can spy on navigation without mocking window.location (non-configurable in JSDOM v26). */
        _navigateTo(url: string) {
            window.location.href = url;
        },

        async copyCliCommand() {
            try {
                await Shopware.Utils.dom.copyStringToClipboard(this.cliUpgradeCommand);
                useSnackbar().addSnackbar({
                    message: this.$t('global.sw-field.notification.notificationCopySuccessMessage'),
                    variant: 'success',
                });
            } catch {
                useSnackbar().addSnackbar({
                    message: this.$t('global.sw-field.notification.notificationCopyFailureMessage'),
                    variant: 'error',
                });
            }
        },

        createdComponent() {
            void this.updateService.checkForUpdates().then((response) => {
                this.autoUpdateEnabled = response.autoUpdateEnabled !== false;
                this.clusterSetup = response.clusterSetup === true;
                this.updateInfo = response;

                if (!response.version) {
                    this.isLoading = false;

                    return;
                }

                void Promise.all([
                    this.updateService.checkLicense(),
                    this.updateService.extensionCompatibility(),
                ]).then(
                    ([
                        licenseCheck,
                        extensions,
                    ]) => {
                        this.licenseValid = licenseCheck.isValid === true;
                        this.extensions = extensions;

                        if (this.displayUnknownExtensionsWarning && this.displayIncompatibleExtensionsWarning) {
                            this.chosenExtensionBehaviour = 'all';
                        } else if (this.displayIncompatibleExtensionsWarning) {
                            this.chosenExtensionBehaviour = 'notCompatible';
                        }

                        this.isLoading = false;
                    },
                );
            });
        },

        startUpdateProcess() {
            this.updateModalShown = false;
            this.$emit('update-started');
            this.updaterIsRunning = true;
            this.createNotificationSuccess({
                message: this.$t('sw-settings-shopware-updates.notifications.updateStarted'),
            });

            this.downloadRecovery();
        },

        stopUpdateProcess() {
            this.updateModalShown = false;
            this.$emit('update-stopped');
            this.updaterIsRunning = false;
            this.createNotificationInfo({
                message: this.$t('sw-settings-shopware-updates.notifications.updateStopped'),
            });
        },

        downloadRecovery() {
            this.updateService
                .downloadRecovery()
                .then(() => {
                    this.progressbarValue = 0;
                    this.deactivateExtensions(0);
                })
                .catch(() => {
                    this.createNotificationError({
                        message: this.$t('sw-settings-shopware-updates.notifications.downloadFailed'),
                    });
                });
        },

        deactivateExtensions(offset: number) {
            this.step = 'deactivate';
            this.updateService
                .deactivateExtensions(offset, this.chosenExtensionBehaviour)
                .then((response) => {
                    this.progressbarValue = Math.floor((response.offset / response.total) * 100);

                    if (response.offset === response.total) {
                        this.redirectToPage(this.buildRecoveryUrl());
                    } else {
                        this.deactivateExtensions(response.offset);
                    }
                })
                .catch((e: ShopwareApiError) => {
                    this.stopUpdateProcess();

                    const context = {
                        code: e.response.data.errors[0].code,
                        meta: e.response.data.errors[0].meta,
                    };

                    if (context.code === 'FRAMEWORK__PLUGIN_HAS_DEPENDANTS') {
                        this.createNotificationWarning({
                            // @ts-expect-error
                            message: this.$t('sw-extension.errors.messageDeactivationFailedDependencies', null, null, {
                                dependency: context.meta.parameters.dependency,
                                dependantNames: context.meta.parameters.dependantNames,
                            }),
                        });
                    } else if (context.code === 'THEME__THEME_ASSIGNMENT') {
                        this.createNotificationWarning({
                            // @ts-expect-error
                            message: this.$t('sw-extension.errors.messageDeactivationFailedThemeAssignment', null, null, {
                                themeName: context.meta.parameters.themeName,
                                assignments: context.meta.parameters.assignments,
                            }),
                        });
                    } else {
                        this.createNotificationError({
                            message: this.$t('sw-settings-shopware-updates.notifications.deactivationFailed'),
                        });
                    }
                });
        },

        buildRecoveryUrl(): string {
            const url = `${Shopware.Context.api.basePath}/shopware-installer.phar.php`;
            const locale = useSession().currentLocale.value ?? '';

            return locale === '' ? url : `${url}?language=${encodeURIComponent(locale)}`;
        },

        redirectToPage(url: string) {
            this._navigateTo(url);
        },
    },
});

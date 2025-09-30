import template from './sw-settings-shopware-updates-wizard.html.twig';
import './sw-settings-shopware-updates-wizard.scss';

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
        requirements: Array<{ result: boolean }>;
        plugins: Array<{ statusName: string }>;
        isLoading: boolean;
        checkedBackupCheckbox: boolean;
        updateRunning: boolean;
        progressbarValue: number;
        step: string;
        updaterIsRunning: boolean;
        updateModalShown: boolean;
        chosenPluginBehaviour: string;
    } {
        return {
            updateInfo: {
                version: null,
                changelog: null,
            },
            requirements: [],
            plugins: [],
            isLoading: true,
            checkedBackupCheckbox: false,
            updateRunning: false,
            progressbarValue: 0,
            step: 'download',
            updaterIsRunning: false,
            updateModalShown: false,
            chosenPluginBehaviour: '',
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(),
        };
    },
    computed: {
        updatePossible() {
            // Check if the result of every requirement is true. If it's the case, return true, otherwise return false.
            return this.requirements.every((requirement) => requirement.result === true);
        },

        updateButtonTooltip() {
            if (this.updatePossible) {
                return {
                    message: '',
                    disabled: true,
                };
            }

            return {
                message: this.$tc('sw-settings-shopware-updates.infos.requirementsNotMet'),
                position: 'bottom',
            };
        },

        displayIncompatiblePluginsWarning() {
            return this.plugins.some((plugin) => {
                return plugin.statusName !== 'compatible' && plugin.statusName !== 'notInStore';
            });
        },

        displayUnknownPluginsWarning() {
            return this.plugins.some((plugin) => {
                return plugin.statusName === 'notInStore';
            });
        },

        displayAllPluginsOkayInfo() {
            return !(this.displayIncompatiblePluginsWarning || this.displayUnknownPluginsWarning);
        },

        optionDeactivateIncompatibleTranslation() {
            const deactivateIncompatTrans = this.$tc('sw-settings-shopware-updates.plugins.actions.deactivateIncompatible');
            const isRecommended =
                this.displayIncompatiblePluginsWarning && !this.displayUnknownPluginsWarning
                    ? this.$tc('sw-settings-shopware-updates.plugins.actions.recommended')
                    : '';

            return `${deactivateIncompatTrans} ${isRecommended}`;
        },

        optionDeactivateAllTranslation() {
            const deactiveAllTrans = this.$tc('sw-settings-shopware-updates.plugins.actions.deactivateAll');
            const isRecommended =
                this.displayIncompatiblePluginsWarning && this.displayUnknownPluginsWarning
                    ? this.$tc('sw-settings-shopware-updates.plugins.actions.recommended')
                    : '';

            return `${deactiveAllTrans} ${isRecommended}`;
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            void this.updateService.checkForUpdates().then((response) => {
                this.updateInfo = response;

                if (response.version) {
                    void this.updateService.checkRequirements().then((requirementsStore) => {
                        this.onRequirementsResponse(requirementsStore);
                    });
                } else {
                    this.isLoading = false;
                }
            });
        },

        onRequirementsResponse(requirementsStore: Array<{ result: boolean }>) {
            this.requirements = requirementsStore;
            void this.updateService.extensionCompatibility().then((plugins) => {
                this.plugins = plugins;

                if (this.displayUnknownPluginsWarning && this.displayIncompatiblePluginsWarning) {
                    this.chosenPluginBehaviour = 'all';
                } else if (this.displayIncompatiblePluginsWarning) {
                    this.chosenPluginBehaviour = 'notCompatible';
                }

                this.isLoading = false;
            });
        },

        startUpdateProcess() {
            this.updateModalShown = false;
            this.$emit('update-started');
            this.updaterIsRunning = true;
            this.createNotificationSuccess({
                message: this.$tc('sw-settings-shopware-updates.notifications.updateStarted'),
            });

            this.downloadRecovery();
        },

        stopUpdateProcess() {
            this.updateModalShown = false;
            this.$emit('update-stopped');
            this.updaterIsRunning = false;
            this.createNotificationInfo({
                message: this.$tc('sw-settings-shopware-updates.notifications.updateStopped'),
            });
        },

        downloadRecovery() {
            this.updateService
                .downloadRecovery()
                .then(() => {
                    this.progressbarValue = 0;
                    this.deactivatePlugins(0);
                })
                .catch(() => {
                    this.createNotificationError({
                        message: this.$tc('sw-settings-shopware-updates.notifications.downloadFailed'),
                    });
                });
        },

        deactivatePlugins(offset: number) {
            this.step = 'deactivate';
            this.updateService
                .deactivatePlugins(offset, this.chosenPluginBehaviour)
                .then((response) => {
                    this.progressbarValue = Math.floor((response.offset / response.total) * 100);

                    if (response.offset === response.total) {
                        this.redirectToPage(`${Shopware.Context.api.basePath}/shopware-installer.phar.php`);
                    } else {
                        this.deactivatePlugins(response.offset);
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
                            message: this.$tc('sw-extension.errors.messageDeactivationFailedThemeAssignment', null, null, {
                                themeName: context.meta.parameters.themeName,
                                assignments: context.meta.parameters.assignments,
                            }),
                        });
                    } else {
                        this.createNotificationError({
                            message: this.$tc('sw-settings-shopware-updates.notifications.deactivationFailed'),
                        });
                    }
                });
        },

        redirectToPage(url: string) {
            window.location.href = url;
        },
    },
});

import template from './sw-settings-shopware-updates-wizard.html.twig';
import './sw-settings-shopware-updates-wizard.scss';

const { Component, Mixin } = Shopware;

Component.register('sw-settings-shopware-updates-wizard', {
    template,

    inject: ['updateService'],
    mixins: [
        Mixin.getByName('notification')
    ],

    data() {
        return {
            updateInfo: {
                version: null,
                changelog: null
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
            chosenPluginBehaviour: ''
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle()
        };
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.updateService.checkForUpdates().then(response => {
                this.updateInfo = response;

                if (response.version) {
                    this.updateService.checkRequirements().then(requirementsStore => {
                        this.onRequirementsResponse(requirementsStore);
                    });
                } else {
                    this.isLoading = false;
                }
            });
        },
        onRequirementsResponse(requirementsStore) {
            this.requirements = requirementsStore;
            this.updateService.pluginCompatibility().then(plugins => {
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
                title: this.$tc('sw-settings-shopware-updates.notifications.title'),
                message: this.$tc('sw-settings-shopware-updates.notifications.updateStarted')
            });

            this.downloadUpdate(0);
        },

        downloadUpdate(offset) {
            this.updateService.downloadUpdate(offset).then(response => {
                this.progressbarValue = (Math.floor((response.offset / response.total) * 100));

                if (response.offset === response.total && response.success) {
                    this.progressbarValue = 0;
                    this.deactivatePlugins(0);
                } else if (response.offset !== response.total && response.success) {
                    this.downloadUpdate(response.offset);
                } else {
                    this.createNotificationError({
                        title: this.$tc('sw-settings-shopware-updates.notifications.title'),
                        message: this.$tc('sw-settings-shopware-updates.notifications.downloadFailed')
                    });
                }
            }).catch(() => {
                this.createNotificationError({
                    title: this.$tc('sw-settings-shopware-updates.notifications.title'),
                    message: this.$tc('sw-settings-shopware-updates.notifications.downloadFailed')
                });
            });
        },

        deactivatePlugins(offset) {
            this.step = 'deactivate';
            this.updateService.deactivatePlugins(offset, this.chosenPluginBehaviour).then(response => {
                this.progressbarValue = (Math.floor((response.offset / response.total) * 100));

                if (response.offset === response.total && response.success) {
                    this.progressbarValue = 0;
                    this.unpackUpdate(0);
                } else if (response.offset !== response.total && response.success) {
                    this.deactivatePlugins(response.offset);
                } else {
                    this.createNotificationError({
                        title: this.$tc('sw-settings-shopware-updates.notifications.title'),
                        message: this.$tc('sw-settings-shopware-updates.notifications.deactivationFailed')
                    });
                }
            }).catch(() => {
                this.createNotificationError({
                    title: this.$tc('sw-settings-shopware-updates.notifications.title'),
                    message: this.$tc('sw-settings-shopware-updates.notifications.deactivationFailed')
                });
            });
        },

        unpackUpdate(offset) {
            this.step = 'unpack';
            this.updateService.unpackUpdate(offset).then(response => {
                this.progressbarValue = (Math.floor((response.offset / response.total) * 100));

                if (response.redirectTo) {
                    window.location.href = response.redirectTo;
                } else if (response.offset !== response.total && response.success) {
                    this.unpackUpdate(response.offset);
                } else {
                    this.createNotificationError({
                        title: this.$tc('sw-settings-shopware-updates.notifications.title'),
                        message: this.$tc('sw-settings-shopware-updates.notifications.unpackFailed')
                    });
                }
            });
        }
    },
    computed: {
        updatePossible() {
            let requirementsMet = true;
            this.requirements.forEach(item => {
                if (item.result === 0) {
                    requirementsMet = false;
                }
            });

            return requirementsMet;
        },
        updateButtonTooltip() {
            if (this.updatePossible) {
                return {
                    message: '',
                    disabled: true
                };
            }

            return {
                message: this.$tc('sw-settings-shopware-updates.infos.requirementsNotMet'),
                position: 'bottom'
            };
        },
        changelog() {
            if (!this.updateInfo.version) {
                return '';
            }

            if (this.$i18n.locale.substr(0, 2) === 'de') {
                return this.updateInfo.changelog.de.changelog;
            }

            return this.updateInfo.changelog.en.changelog;
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
            const isRecommended = this.displayIncompatiblePluginsWarning && !this.displayUnknownPluginsWarning ?
                this.$tc('sw-settings-shopware-updates.plugins.actions.recommended') : '';

            return `${deactivateIncompatTrans} ${isRecommended}`;
        },
        optionDeactivateAllTranslation() {
            const deactiveAllTrans = this.$tc('sw-settings-shopware-updates.plugins.actions.deactivateAll');
            const isRecommended = this.displayIncompatiblePluginsWarning && this.displayUnknownPluginsWarning ?
                this.$tc('sw-settings-shopware-updates.plugins.actions.recommended') : '';

            return `${deactiveAllTrans} ${isRecommended}`;
        }
    }
});

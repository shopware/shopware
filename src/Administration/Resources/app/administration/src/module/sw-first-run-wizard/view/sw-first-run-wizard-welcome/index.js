import template from './sw-first-run-wizard-welcome.html.twig';
import './sw-first-run-wizard-welcome.scss';

const { Component } = Shopware;
const { Criteria } = Shopware.Data;
const cacheApiService = Shopware.Service('cacheApiService');
const extensionStoreActionService = Shopware.Service('extensionStoreActionService');

Component.register('sw-first-run-wizard-welcome', {
    template,

    inject: [
        'languagePluginService',
        'userService',
        'loginService',
        'repositoryFactory',
        'storeService',
    ],

    mixins: [
        'notification',
    ],

    data() {
        return {
            languages: [],
            languagePlugins: [],
            latestTouchedPlugin: null,
            showConfirmLanguageSwitchModal: false,
            newLocaleId: null,
            user: { localeId: '', pw: '' },
            userProfile: {},
            userPromise: null,
            isLoading: false,
        };
    },

    computed: {
        languageRepository() {
            return this.repositoryFactory.create('language');
        },

        snippetRepository() {
            return this.repositoryFactory.create('snippet_set');
        },

        userRepository() {
            return this.repositoryFactory.create('user');
        },

        languageId() {
            return Shopware.State.get('session').languageId;
        },

        localeRepository() {
            return this.repositoryFactory.create('locale');
        },

        languageCriteria() {
            return this.getLanguageCriteria();
        },

        snippetCriteria() {
            const snippetCriteria = new Criteria();
            snippetCriteria.setLimit(10);
            return snippetCriteria;
        },
    },

    watch: {
        languageId() {
            this.createdComponent();
        },
    },

    beforeMount() {
        this.beforeMountComponent();
    },

    created() {
        this.createdComponent();
        this.runOnce();
    },

    methods: {
        beforeMountComponent() {
            this.userPromise.then((user) => {
                this.user = user;
            });
        },

        runOnce() {
            this.installMissingLanguages();
        },

        createdComponent() {
            this.updateButtons();
            this.setTitle();
            this.getLanguagePlugins();

            const languagePromise = new Promise((resolve) => {
                resolve(this.languageId);
            });

            this.userPromise = this.userService.getUser().then((response) => {
                return this.setUserData(response.data);
            });

            const promises = [
                languagePromise,
                this.userPromise,
            ];

            Promise.all(promises).then(() => {
                this.loadLanguages();
            });
        },

        setTitle() {
            this.$emit('frw-set-title', this.$tc('sw-first-run-wizard.welcome.modalTitle'));
        },

        updateButtons() {
            const buttonConfig = [
                {
                    key: 'next',
                    label: this.$tc('sw-first-run-wizard.general.buttonNext'),
                    position: 'right',
                    variant: 'primary',
                    action: 'sw.first.run.wizard.index.data-import',
                    disabled: false,
                },
            ];

            this.$emit('buttons-update', buttonConfig);
        },

        setUserData(userProfile) {
            this.userProfile = userProfile;
            return new Promise((resolve) => {
                resolve(this.userRepository.get(this.userProfile.id));
            });
        },

        getLanguagePlugins() {
            const language = Shopware.State.get('session').currentLocale;

            this.languagePluginService.getPlugins({
                language,
            }).then((response) => {
                this.languagePlugins = response.items;
            });
        },

        onPluginInstalled(plugin) {
            this.latestTouchedPlugin = this.getPluginByName(plugin);

            this.getLanguagePlugins();
            this.isLoading = true;
            this.loadLanguages().then(() => {
                this.showConfirmLanguageSwitchModal = true;
                this.isLoading = false;
            });
        },

        onPluginRemoved(plugin) {
            this.latestTouchedPlugin = this.getPluginByName(plugin);

            this.getLanguagePlugins();
        },

        onConfirmLanguageSwitch() {
            this.loginService.verifyUserToken(this.user.pw).then((verifiedToken) => {
                const context = { ...Shopware.Context.api };
                context.authToken.access = verifiedToken;

                this.userRepository.save(this.user, context)
                    .then(async () => {
                        await Shopware.Service('localeHelper').setLocaleWithId(this.user.localeId);

                        document.location.reload();
                    })
                    .finally(() => {
                        this.showConfirmLanguageSwitchModal = false;
                    });
            }).catch(() => {
                this.createNotificationError({
                    title: this.$tc('sw-settings-user.user-detail.passwordConfirmation.notificationPasswordErrorTitle'),
                    message: this.$tc('sw-settings-user.user-detail.passwordConfirmation.notificationPasswordErrorMessage'),
                });
            }).finally(() => {
                this.confirmPassword = '';
            });
        },

        onCancelSwitch() {
            this.showConfirmLanguageSwitchModal = false;
        },

        getPlugins() {
            return this.languagePluginService.getPlugins({}).then((response) => {
                this.languagePlugins = response.items;
            });
        },

        getPluginByName(name) {
            if (this.languagePlugins.length < 1) {
                return null;
            }

            return this.languagePlugins
                .find((p) => p.name === name);
        },

        getPluginByLanguageName(name) {
            return this.getPluginByName(`SwagI18n${name}`);
        },

        /**
         * Notice: only because the plugin failed to download doesnt mean the installation process has to fail.
         * Plugins may already be downloaded so the installation can still be done using that version.
         * @param pluginName
         * @returns {Promise<void>}
         */
        async setupPlugin(pluginName) {
            let errCode = 'noErrors';
            let catchedError = null;
            let errMessage = null;

            try {
                await this.storeService.downloadPlugin(pluginName, true, true);
            } catch (e) {
                errCode = 'downloadFailed';
                catchedError = e;
            }

            try {
                await extensionStoreActionService.installExtension(pluginName, 'plugin');
            } catch (e) {
                if (errCode !== 'downloadFailed') {
                    errCode = 'installationFailed';
                    errMessage = this.$tc('sw-first-run-wizard.welcome.pluginInstallationFailedMessage');
                    catchedError = e;
                }
            }

            try {
                await extensionStoreActionService.activateExtension(pluginName, 'plugin');
            } catch (e) {
                if (errCode === 'noErrors') {
                    errCode = 'activationFailed';
                    errMessage = this.$tc('sw-first-run-wizard.welcome.pluginActivationFailedMessage');
                    catchedError = e;
                }
            }

            cacheApiService.clear();

            if (errCode !== 'noErrors') {
                this.showPluginErrorNotification(errMessage, catchedError);
                throw new Error('Plugin could not be installed');
            }
        },

        loadSnippets() {
            return this.snippetRepository.search(this.snippetCriteria).then((result) => {
                return result.map(snippet => snippet.iso);
            });
        },

        getLanguageCriteria() {
            const languageCriteria = new Criteria();
            languageCriteria.addAssociation('locale');
            languageCriteria.addSorting(Criteria.sort('locale.name', 'ASC'));
            languageCriteria.addSorting(Criteria.sort('locale.territory', 'ASC'));
            languageCriteria.limit = null;

            return languageCriteria;
        },

        makeLanguageNameArrayFromObjects(languageObjects) {
            const langNameArray = [];

            languageObjects.forEach((languageObject) => {
                langNameArray.push(languageObject.name);
            });

            return langNameArray;
        },

        getMissingSnippets() {
            const languageCriteria = this.getLanguageCriteria();

            return this.languageRepository.search(languageCriteria).then(async (result) => {
                const snippets = await this.loadSnippets();
                const missingSnippets = [];

                if (!this.languagePlugins) {
                    this.showPluginErrorNotification(this.$tc('sw-first-run-wizard.welcome.noConnectionMessage')
                        + this.$tc('sw-first-run-wizard.welcome.tryAgainLater'));
                    return null;
                }
                await this.getLanguagePlugins();
                const offeredLanguagePluginNames = await this.makeLanguageNameArrayFromObjects(this.languagePlugins);

                result.forEach((lang) => {
                    if (snippets.indexOf(lang.locale.code) !== -1 ||
                        lang.locale.code === 'en-GB' ||
                        lang.locale.code === 'de-DE') return;

                    const snippetPlugin = this.getPluginByLanguageName(lang.locale.name);

                    if (!snippetPlugin) {
                        if (offeredLanguagePluginNames.indexOf(`SwagI18n${lang.locale.name}`) !== -1) {
                            this.showPluginNotFoundNotification(lang.locale.name);
                        }
                        return;
                    }
                    missingSnippets.push(snippetPlugin.name);
                });
                return missingSnippets;
            });
        },

        showPluginErrorNotification(message, errorMessage) {
            const tryLater = this.$tc('sw-first-run-wizard.welcome.tryAgainLater');

            this.createNotificationError({
                message: `${message}\n${errorMessage}\n${tryLater}`,
            });
        },

        showPluginNotFoundNotification(name, errorMessage = '') {
            const message = this.$tc('sw-first-run-wizard.welcome.pluginNotFoundMessage', 0, { languageName: name });
            this.showPluginErrorNotification(message, errorMessage);
        },

        setupMissingPlugins(missingSnippets) {
            const setupPluginPromises = missingSnippets.map((missingPluginName, i) => {
                return this.setupPlugin(missingPluginName).catch(() => {
                    missingSnippets.splice(i, 1);
                });
            });
            return Promise.all(setupPluginPromises);
        },

        async installMissingLanguages() {
            await this.getLanguagePlugins();
            let missingSnippets = await this.getMissingSnippets();

            if (missingSnippets.length <= 0) {
                return;
            }

            this.isLoading = true;
            missingSnippets = await this.setupMissingPlugins(missingSnippets);

            if (missingSnippets.length > 0 && missingSnippets[0] != null) {
                const installedPlugins = await missingSnippets.join(', ');

                this.createNotification({
                    message: this.$tc('sw-first-run-wizard.welcome.pluginsInstalledMessage', missingSnippets.length)
                        + installedPlugins,
                });

                this.onPluginInstalled(missingSnippets[missingSnippets.length - 1]);
            }
            this.isLoading = false;
        },

        loadLanguages() {
            return this.languageRepository.search(this.languageCriteria).then((result) => {
                this.languages = [];

                result.forEach((lang) => {
                    lang.customLabel = `${lang.locale.translated.name} (${lang.locale.translated.territory})`;
                    this.languages.push(lang);
                });

                return this.languages;
            });
        },
    },
});

import template from './sw-first-run-wizard-welcome.html.twig';
import './sw-first-run-wizard-welcome.scss';

const { Component } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('sw-first-run-wizard-welcome', {
    template,

    inject: [
        'languagePluginService',
        'userService',
        'loginService',
        'repositoryFactory'
    ],

    data() {
        return {
            languages: [],
            languagePlugins: [],
            latestTouchedPlugin: null,
            showConfirmLanguageSwitchModal: false,
            newLocaleId: null,
            user: { localeId: '' },
            userProfile: {},
            userPromise: null,
            isLoading: false
        };
    },

    computed: {
        languageRepository() {
            return this.repositoryFactory.create('language');
        },

        userRepository() {
            return this.repositoryFactory.create('user');
        },

        languageId() {
            return Shopware.State.get('session').languageId;
        },

        localeRepository() {
            return this.repositoryFactory.create('locale');
        }
    },

    watch: {
        languageId() {
            this.createdComponent();
        }
    },

    beforeMount() {
        this.beforeMountComponent();
    },

    created() {
        this.createdComponent();
    },

    methods: {
        beforeMountComponent() {
            this.userPromise.then((user) => {
                this.user = user;
            });
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
                this.userPromise
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
                    disabled: false
                }
            ];

            this.$emit('buttons-update', buttonConfig);
        },

        setUserData(userProfile) {
            this.userProfile = userProfile;
            return new Promise((resolve) => {
                resolve(this.userRepository.get(this.userProfile.id, Shopware.Context.api));
            });
        },

        getLanguagePlugins() {
            const language = Shopware.State.get('session').currentLocale;

            this.languagePluginService.getPlugins({
                language
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
            this.userRepository.save(this.user, Shopware.Context.api)
                .then(() => {
                    this.showConfirmLanguageSwitchModal = false;

                    this.localeRepository.get(this.user.localeId, Shopware.Context.api).then(({ code }) => {
                        Shopware.State.dispatch('setAdminLocale', code);
                        window.localStorage.setItem('sw-admin-locale', code);
                        document.location.reload();
                    });
                })
                .catch(() => {
                    this.showConfirmLanguageSwitchModal = false;
                });
        },

        onCancelSwitch() {
            this.showConfirmLanguageSwitchModal = false;
        },

        getPluginByName(name) {
            if (this.languagePlugins.length < 1) {
                return null;
            }

            const plugin = this.languagePlugins
                .find((p) => p.name === name);

            return plugin;
        },

        loadLanguages() {
            const languageCriteria = new Criteria();
            languageCriteria.addAssociation('locale');
            languageCriteria.addSorting(Criteria.sort('locale.name', 'ASC'));
            languageCriteria.addSorting(Criteria.sort('locale.territory', 'ASC'));
            languageCriteria.limit = 10;

            return this.languageRepository.search(languageCriteria, Shopware.Context.api).then((result) => {
                this.languages = [];
                result.forEach((lang) => {
                    lang.customLabel = `${lang.locale.translated.name} (${lang.locale.translated.territory})`;
                    this.languages.push(lang);
                });

                return this.languages;
            });
        }
    }
});

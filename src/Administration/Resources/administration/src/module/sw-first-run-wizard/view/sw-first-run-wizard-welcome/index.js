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
        'repositoryFactory',
        'apiContext'
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
            return this.$store.state.adminLocale.languageId;
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

        setUserData(userProfile) {
            this.userProfile = userProfile;
            return new Promise((resolve) => {
                resolve(this.userRepository.get(this.userProfile.id, this.apiContext));
            });
        },

        getLanguagePlugins() {
            const language = this.$store.state.adminLocale.currentLocale;

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
            this.userRepository.save(this.user, this.apiContext)
                .then(() => {
                    this.showConfirmLanguageSwitchModal = false;

                    this.localeRepository.get(this.user.localeId, this.apiContext).then(({ code }) => {
                        this.$store.dispatch('setAdminLocale', code);
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

            return this.languageRepository.search(languageCriteria, this.apiContext).then((result) => {
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

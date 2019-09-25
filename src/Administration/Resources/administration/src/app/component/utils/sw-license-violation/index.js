import { mapState } from 'vuex';
import template from './sw-license-violation.html.twig';
import './sw-license-violation.scss';

/**
 * @private
 */
Shopware.Component.register('sw-license-violation', {
    template,

    inject: [
        'licenseViolationService',
        'pluginService',
        'cacheApiService',
        'loginService',
        'repositoryFactory',
        'context'
    ],

    mixins: [
        Shopware.Mixin.getByName('notification')
    ],

    data() {
        return {
            licenseSubscription: null,
            showViolation: false,
            readNotice: false,
            loading: []
        };
    },

    computed: {
        ...mapState('licenseViolation', [
            'violations',
            'warnings'
        ]),

        visible() {
            if (!this.showViolation) {
                return false;
            }

            return this.violations.length > 0;
        },

        pluginRepository() {
            return this.repositoryFactory.create('plugin');
        },

        pluginCriteria() {
            return new Shopware.Data.Criteria(1, 50);
        },

        isLoading() {
            return this.loading.length > 0;
        }
    },

    watch: {
        $route: {
            handler() {
                this.$nextTick(() => {
                    this.getPluginViolation();
                });
            },
            immediate: true
        },
        visible: {
            handler(newValue) {
                if (newValue !== true) {
                    return;
                }

                this.fetchPlugins();
            },
            immediate: true
        }
    },

    methods: {
        getPluginViolation() {
            if (!this.loginService.isLoggedIn()) {
                return Promise.resolve();
            }

            this.showViolation = this.licenseViolationService.isTimeExpired(
                this.licenseViolationService.key.showViolationsKey
            );

            this.addLoading('getPluginViolation');

            return this.licenseViolationService.checkForLicenseViolations()
                .then(({ violations, warnings, other }) => {
                    this.$store.commit('licenseViolation/setViolations', violations);
                    this.$store.commit('licenseViolation/setWarnings', warnings);
                    this.$store.commit('licenseViolation/setOther', other);
                })
                .finally(() => {
                    this.finishLoading('getPluginViolation');
                });
        },

        reloadViolations() {
            this.licenseViolationService.resetLicenseViolations();

            return this.getPluginViolation();
        },

        deactivateTempoarary() {
            this.licenseViolationService.saveTimeToLocalStorage(this.licenseViolationService.key.showViolationsKey);

            this.readNotice = false;
            this.showViolation = this.licenseViolationService.isTimeExpired(
                this.licenseViolationService.key.showViolationsKey
            );
        },

        fetchPlugins() {
            if (!this.loginService.isLoggedIn()) {
                return;
            }

            this.addLoading('fetchPlugins');

            this.pluginRepository.search(this.pluginCriteria, this.context)
                .then((response) => {
                    this.plugins = response;
                })
                .finally(() => {
                    this.finishLoading('fetchPlugins');
                });
        },

        deletePlugin(violation) {
            this.addLoading('deletePlugin');

            const matchingPlugin = this.plugins.find((plugin) => plugin.name === violation.name);

            return this.licenseViolationService.forceDeletePlugin(this.pluginService, matchingPlugin)
                .then(() => {
                    this.createNotificationSuccess({
                        title: this.$tc('sw-plugin.list.titleDeleteSuccess'),
                        message: this.$tc('sw-plugin.list.messageDeleteSuccess')
                    });

                    return this.reloadViolations();
                })
                .finally(() => {
                    this.finishLoading('deletePlugin');
                });
        },

        getPluginForViolation(violation) {
            if (!Array.isArray(this.plugins)) {
                return null;
            }

            const matchingPlugin = this.plugins.find((plugin) => {
                return plugin.name === violation.name;
            });

            return matchingPlugin || null;
        },

        addLoading(key) {
            this.loading.push(key);
        },

        finishLoading(key) {
            this.loading = this.loading.filter((value) => value !== key);
        }
    }
});

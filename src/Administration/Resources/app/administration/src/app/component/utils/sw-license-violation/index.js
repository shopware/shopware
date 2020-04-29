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
        'loginService'

    ],

    mixins: [
        Shopware.Mixin.getByName('notification')
    ],

    data() {
        return {
            licenseSubscription: null,
            showViolation: false,
            readNotice: false,
            loading: [],
            showDeleteModal: false,
            deletePluginItem: null
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
            const repositoryFactory = Shopware.Service('repositoryFactory');
            return repositoryFactory.create('plugin');
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
                    Shopware.State.commit('licenseViolation/setViolations', violations);
                    Shopware.State.commit('licenseViolation/setWarnings', warnings);
                    Shopware.State.commit('licenseViolation/setOther', other);
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

            this.pluginRepository.search(this.pluginCriteria, Shopware.Context.api)
                .then((response) => {
                    this.plugins = response;
                })
                .finally(() => {
                    this.finishLoading('fetchPlugins');
                });
        },

        deletePlugin(violation) {
            this.deletePluginItem = violation;
            this.showDeleteModal = true;
        },

        onCloseDeleteModal() {
            this.deletePluginItem = null;
            this.showDeleteModal = false;
        },

        onConfirmDelete() {
            const violation = this.deletePluginItem;

            this.showDeleteModal = false;
            this.addLoading('deletePlugin');

            const matchingPlugin = this.plugins.find((plugin) => plugin.name === violation.name);

            return this.licenseViolationService.forceDeletePlugin(this.pluginService, matchingPlugin)
                .then(() => {
                    this.createNotificationSuccess({
                        title: this.$tc('global.default.success'),
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

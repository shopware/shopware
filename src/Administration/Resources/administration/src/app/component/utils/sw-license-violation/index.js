import template from './sw-license-violation.html.twig';
import './sw-license-violation.scss';

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
            violations: [],
            readNotice: false,
            loading: []
        };
    },

    computed: {
        visible() {
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

            this.addLoading('getPluginViolation');

            return this.licenseViolationService.checkForLicenseViolations()
                .then(({ violations }) => {
                    this.violations = violations;
                })
                .finally(() => {
                    this.finishLoading('getPluginViolation');
                });
        },

        reloadViolations() {
            localStorage.removeItem(this.licenseViolationService.key.showViolationsKey);
            localStorage.removeItem(this.licenseViolationService.key.lastLicenseFetchedKey);
            localStorage.removeItem(this.licenseViolationService.key.responseCacheKey);

            return this.getPluginViolation();
        },

        deactivateTempoarary() {
            this.licenseViolationService.saveTimeToLocalStorage(this.licenseViolationService.key.showViolationsKey);

            this.violations = [];
            this.readNotice = false;
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

        async deletePlugin(violation) {
            this.addLoading('deletePlugin');

            try {
                const matchingPlugin = this.plugins.find((plugin) => plugin.name === violation.name);
                const isActive = matchingPlugin.active;
                const isInstalled = matchingPlugin.installedAt !== null;

                if (isActive) {
                    await this.pluginService.deactivate(matchingPlugin.name);
                    await this.cacheApiService.clear();
                }

                if (isInstalled) {
                    await this.pluginService.uninstall(matchingPlugin.name);
                    await this.cacheApiService.clear();
                }

                await this.pluginService.delete(matchingPlugin.name);
                await this.cacheApiService.clear();

                this.createNotificationSuccess({
                    title: this.$tc('sw-plugin.list.titleDeleteSuccess'),
                    message: this.$tc('sw-plugin.list.messageDeleteSuccess')
                });
            } catch (error) {
                throw new Error(error);
            }

            await this.reloadViolations();
            this.finishLoading('deletePlugin');
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

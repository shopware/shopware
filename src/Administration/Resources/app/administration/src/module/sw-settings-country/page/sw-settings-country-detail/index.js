/**
 * @sw-package fundamentals@discovery
 */
import template from './sw-settings-country-detail.html.twig';
import './sw-settings-country-detail.scss';

const { Component, Mixin } = Shopware;
const { mapPropertyErrors } = Component.getComponentHelper();

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: [
        'repositoryFactory',
        'acl',
        'customFieldDataProviderService',
        'feature',
    ],

    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('placeholder'),
        Mixin.getByName('discard-detail-page-changes')('country'),
    ],

    shortcuts: {
        'SYSTEMKEY+S': {
            active() {
                return this.allowSave;
            },
            method: 'onSave',
        },
        ESCAPE: 'onCancel',
    },

    data() {
        return {
            country: {
                customerTax: {
                    enabled: false,
                },
                companyTax: {
                    enabled: false,
                },
            },
            countryId: null,
            isLoading: false,
            countryStateRepository: null,
            isSaveSuccessful: false,
            customFieldSets: null,
            userConfig: {
                value: {},
            },
            userConfigValues: {},
            showPreviewModal: false,
            previewData: null,
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(this.identifier),
        };
    },

    computed: {
        countryRepository() {
            return this.repositoryFactory.create('country');
        },

        identifier() {
            return this.placeholder(this.country, 'name');
        },

        stateColumns() {
            return this.getStateColumns();
        },

        isNewCountry() {
            return typeof this.country.isNew === 'function' ? this.country.isNew() : false;
        },

        allowSave() {
            return this.isNewCountry ? this.acl.can('country.creator') : this.acl.can('country.editor');
        },

        countryTabs() {
            const createRouteTab = (label, routeName) => {
                const route = {
                    name: routeName,
                };

                return {
                    label: this.$t(label),
                    name: route.name,
                    onClick: () => {
                        void this.$router.push(route);
                    },
                };
            };

            const routePrefix = this.isNewCountry ? 'sw.settings.country.create' : 'sw.settings.country.detail';

            return [
                createRouteTab('sw-settings-country.page.generalTab', `${routePrefix}.general`),
                createRouteTab('sw-settings-country.page.stateTab', `${routePrefix}.state`),
                createRouteTab('sw-settings-country.page.addressHandlingTab', `${routePrefix}.address-handling`),
            ];
        },

        tooltipSave() {
            if (!this.allowSave) {
                return {
                    message: this.$t('sw-privileges.tooltip.warning'),
                    disabled: this.allowSave,
                    showOnDisabledElements: true,
                };
            }

            const systemKey = this.$device.getSystemKey();

            return {
                message: `${systemKey} + S`,
                appearance: 'light',
            };
        },

        ...mapPropertyErrors('country', ['name']),

        showCustomFields() {
            return this.customFieldSets && this.customFieldSets.length > 0;
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            if (!this.$route.params.id) {
                return;
            }

            this.countryId = this.$route.params.id.toLowerCase();

            Promise.all([
                this.loadEntityData(),
                this.loadCustomFieldSets(),
                this.loadUserConfig(),
            ]);
        },

        loadEntityData() {
            if (typeof this.country.isNew === 'function' && this.country.isNew()) {
                return false;
            }

            this.isLoading = true;
            return this.countryRepository
                .get(this.countryId)
                .then((country) => {
                    this.country = country;

                    this.isLoading = false;

                    this.countryStateRepository = this.repositoryFactory.create(
                        this.country.states.entity,
                        this.country.states.source,
                    );
                })
                .catch(() => {
                    this.isLoading = false;
                });
        },

        loadCustomFieldSets() {
            this.customFieldDataProviderService.getCustomFieldSets('country').then((sets) => {
                this.customFieldSets = sets;
            });
        },

        async loadUserConfig() {
            this.userConfig = {
                key: 'setting-country',
                value:
                    (await Shopware.Service('userConfigService').search(['setting-country']))?.data?.['setting-country'] ||
                    [],
            };
            this.userConfigValues = this.userConfig.value[this.countryId];

            if (!this.userConfigValues) {
                this.userConfig.value[this.countryId] = {};
                this.userConfigValues = this.userConfig.value[this.countryId];
            }
        },

        saveFinish() {
            this.isSaveSuccessful = false;
        },

        onSave() {
            this.isSaveSuccessful = false;
            this.isLoading = true;

            const userConfigValue = this.userConfig.value[this.countryId];

            return this.countryRepository
                .save(this.country, Shopware.Context.api)
                .then(() => {
                    if (userConfigValue && Object.keys(userConfigValue).length > 0) {
                        Shopware.Service('userConfigService')
                            .upsert({
                                'setting-country': this.userConfig.value,
                            })
                            .then(() => {
                                this.loadUserConfig();
                            });
                    }
                    this.loadEntityData();
                    this.isLoading = false;
                    this.isSaveSuccessful = true;
                })
                .catch(() => {
                    this.isLoading = false;
                });
        },

        onCancel() {
            this.$router.push({ name: 'sw.settings.country.index' });
        },

        abortOnLanguageChange() {
            return this.countryRepository.hasChanges(this.country);
        },

        saveOnLanguageChange() {
            return this.onSave();
        },

        onChangeLanguage() {
            this.loadEntityData();
        },

        getStateColumns() {
            return [
                {
                    property: 'name',
                    label: this.$t('sw-settings-country.detail.columnStateNameLabel'),
                    inlineEdit: 'string',
                    primary: true,
                },
                {
                    property: 'shortCode',
                    label: this.$t('sw-settings-country.detail.columnStateShortCodeLabel'),
                    inlineEdit: 'string',
                },
            ];
        },

        onSaveModal() {
            return this.onSave();
        },

        /**
         * @param path - Lodash set path
         * @param value
         */
        onUpdateCountry(path, value) {
            Shopware.Utils.object.set(this.country, path, value);
        },
    },
};

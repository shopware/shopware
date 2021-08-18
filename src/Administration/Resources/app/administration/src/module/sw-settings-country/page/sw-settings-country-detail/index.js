import template from './sw-settings-country-detail.html.twig';
import './sw-settings-country-detail.scss';

const { Component, Mixin } = Shopware;
const { mapPropertyErrors } = Component.getComponentHelper();
const { Criteria } = Shopware.Data;

Component.register('sw-settings-country-detail', {
    template,

    inject: [
        'repositoryFactory',
        'acl',
        'customFieldDataProviderService',
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
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(this.identifier),
        };
    },

    computed: {
        currentUserId() {
            return Shopware.State.get('session').currentUser.id;
        },

        countryRepository() {
            return this.repositoryFactory.create('country');
        },

        userConfigRepository() {
            return this.repositoryFactory.create('user_config');
        },

        /**
         * @deprecated tag:v6.5.0 - Will be removed
         * */
        currencyRepository() {
            return this.repositoryFactory.create('currency');
        },

        identifier() {
            return this.placeholder(this.country, 'name');
        },

        stateColumns() {
            return this.getStateColumns();
        },

        isNewCountry() {
            return typeof this.country.isNew === 'function'
                ? this.country.isNew()
                : false;
        },

        allowSave() {
            return this.isNewCountry
                ? this.acl.can('country.creator')
                : this.acl.can('country.editor');
        },

        tooltipSave() {
            if (!this.allowSave) {
                return {
                    message: this.$tc('sw-privileges.tooltip.warning'),
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

        userConfigCriteria() {
            return new Criteria().addFilter(Criteria.multi(
                'AND',
                [
                    Criteria.equals('userId', this.currentUserId),
                    Criteria.equals('key', 'setting-country'),
                ],
            ));
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
            if (!this.$route.params.id) { return; }

            this.countryId = this.$route.params.id;

            Promise.all([
                this.loadEntityData(),
                this.loadCustomFieldSets(),
                this.loadUserConfig(),
            ]);
        },

        loadEntityData() {
            this.isLoading = true;
            return this.countryRepository.get(this.countryId).then(country => {
                this.country = country;

                this.isLoading = false;

                this.countryStateRepository = this.repositoryFactory.create(
                    this.country.states.entity,
                    this.country.states.source,
                );
            });
        },

        loadCustomFieldSets() {
            this.customFieldDataProviderService.getCustomFieldSets('country').then((sets) => {
                this.customFieldSets = sets;
            });
        },

        loadUserConfig() {
            return this.userConfigRepository.search(this.userConfigCriteria, Shopware.Context.api).then((userConfigs) => {
                if (userConfigs.length === 0) {
                    this.userConfig = this.userConfigRepository.create(Shopware.Context.api);
                    this.userConfig.userId = this.currentUserId;
                    this.userConfig.key = 'setting-country';
                    this.userConfig.value = [];
                    return;
                }
                this.userConfig = userConfigs.first();
                this.userConfigValues = this.userConfig.value[this.countryId];

                if (!this.userConfigValues) {
                    this.userConfig.value[this.countryId] = {};
                    this.userConfigValues = this.userConfig.value[this.countryId];
                }
            });
        },

        saveFinish() {
            this.isSaveSuccessful = false;
        },

        onSave() {
            this.isSaveSuccessful = false;
            this.isLoading = true;

            const userConfigValue = this.userConfig.value[this.countryId];

            return this.countryRepository.save(this.country, Shopware.Context.api).then(() => {
                if (userConfigValue
                    && Object.keys(userConfigValue).length > 0) {
                    this.userConfigRepository.save(this.userConfig, Shopware.Context.api)
                        .then(() => {
                            this.loadUserConfig();
                        });
                }

                this.loadEntityData();
                this.isLoading = false;
                this.isSaveSuccessful = true;
            }).catch(() => {
                this.isLoading = false;
            });
        },

        onCancel() {
            this.$router.push({ name: 'sw.settings.country.index' });
        },

        /**
         * @deprecated tag:v6.5.0 - Will be removed
         * */
        countryStateSelectionChanged(selection, selectionCount) {
            this.deleteButtonDisabled = selectionCount <= 0;
        },

        /**
         * @deprecated tag:v6.5.0 - Will be removed
         * */
        onDeleteCountryStates() {
            const selection = this.$refs.countryStateGrid.selection;

            const countryStateIds = Object.keys(selection);
            if (!countryStateIds.length) {
                return Promise.resolve();
            }

            this.countryStateLoading = true;

            return this.countryStateRepository.syncDeleted(countryStateIds, Shopware.Context.api)
                .finally(() => {
                    this.countryStateLoading = false;
                });
        },

        /**
         * @deprecated tag:v6.5.0 - Will be removed
         * */
        onAddCountryState() {
            this.currentCountryState = this.countryStateRepository.create();
        },

        /**
         * @deprecated tag:v6.5.0 - Will be removed
         * */
        onSearchCountryState() {
            this.country.states.criteria.setTerm(this.term);
            this.refreshCountryStateList();
        },

        /**
         * @deprecated tag:v6.5.0 - Will be removed
         * */
        refreshCountryStateList() {
            this.countryStateLoading = true;

            this.$refs.countryStateGrid.load().then(() => {
                this.countryStateLoading = false;
            });
        },

        /**
         * @deprecated tag:v6.5.0 - Will be removed
         * */
        onSaveCountryState() {
            // dont send requests if we are on local mode(creating a new country)
            if (this.country.isNew()) {
                this.country.states.add(this.currentCountryState);
            } else {
                this.countryStateRepository.save(this.currentCountryState).then(() => {
                    this.refreshCountryStateList();
                });
            }

            this.currentCountryState = null;
        },

        /**
         * @deprecated tag:v6.5.0 - Will be removed
         * */
        onCancelCountryState() {
            this.currentCountryState = null;
        },

        /**
         * @deprecated tag:v6.5.0 - Will be removed
         * */
        onClickCountryState(item) {
            // Create a copy with the same id which will be edited
            const copy = this.countryStateRepository.create(Shopware.Context.api, item.id);
            copy._isNew = false;

            this.currentCountryState = Object.assign(copy, item);
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
            return [{
                property: 'name',
                label: this.$tc('sw-settings-country.detail.columnStateNameLabel'),
                inlineEdit: 'string',
                primary: true,
            }, {
                property: 'shortCode',
                label: this.$tc('sw-settings-country.detail.columnStateShortCodeLabel'),
                inlineEdit: 'string',
            }];
        },

        onSaveModal() {
            return this.onSave();
        },
    },
});

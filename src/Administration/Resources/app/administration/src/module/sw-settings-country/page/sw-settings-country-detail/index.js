import template from './sw-settings-country-detail.html.twig';
import './sw-settings-country-detail.scss';

const { Component, Mixin } = Shopware;
const { mapPropertyErrors } = Component.getComponentHelper();

Component.register('sw-settings-country-detail', {
    template,

    inject: ['repositoryFactory', 'acl'],

    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('placeholder'),
        Mixin.getByName('discard-detail-page-changes')('country')
    ],

    shortcuts: {
        'SYSTEMKEY+S': {
            active() {
                return this.allowSave;
            },
            method: 'onSave'
        },
        ESCAPE: 'onCancel'
    },

    data() {
        return {
            country: {},
            term: null,
            countryId: null,
            isLoading: false,
            currentCountryState: null,
            countryStateRepository: null,
            countryStateLoading: false,
            isSaveSuccessful: false,
            deleteButtonDisabled: true
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(this.identifier)
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
                    showOnDisabledElements: true
                };
            }

            const systemKey = this.$device.getSystemKey();

            return {
                message: `${systemKey} + S`,
                appearance: 'light'
            };
        },

        ...mapPropertyErrors('country', ['name'])
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            if (this.$route.params.id) {
                this.countryId = this.$route.params.id;
                this.loadEntityData();
            }
        },

        loadEntityData() {
            this.isLoading = true;
            this.countryRepository.get(this.countryId, Shopware.Context.api).then(country => {
                this.country = country;

                this.isLoading = false;

                this.countryStateRepository = this.repositoryFactory.create(
                    this.country.states.entity,
                    this.country.states.source
                );
            });
        },

        saveFinish() {
            this.isSaveSuccessful = false;
        },

        onSave() {
            this.isSaveSuccessful = false;
            this.isLoading = true;

            return this.countryRepository.save(this.country, Shopware.Context.api).then(() => {
                this.countryRepository.get(this.countryId, Shopware.Context.api).then(country => {
                    this.country = country;
                });
                this.isLoading = false;
                this.isSaveSuccessful = true;
            }).catch(() => {
                this.isLoading = false;
            });
        },

        onCancel() {
            this.$router.push({ name: 'sw.settings.country.index' });
        },

        countryStateSelectionChanged(selection, selectionCount) {
            this.deleteButtonDisabled = selectionCount <= 0;
        },

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

        onAddCountryState() {
            this.currentCountryState = this.countryStateRepository.create(Shopware.Context.api);
        },

        onSearchCountryState() {
            this.country.states.criteria.setTerm(this.term);
            this.refreshCountryStateList();
        },

        refreshCountryStateList() {
            this.countryStateLoading = true;

            this.$refs.countryStateGrid.load().then(() => {
                this.countryStateLoading = false;
            });
        },

        onSaveCountryState() {
            // dont send requests if we are on local mode(creating a new country)
            if (this.country.isNew()) {
                this.country.states.add(this.currentCountryState);
            } else {
                this.countryStateRepository.save(this.currentCountryState, Shopware.Context.api).then(() => {
                    this.refreshCountryStateList();
                });
            }

            this.currentCountryState = null;
        },

        onCancelCountryState() {
            this.currentCountryState = null;
        },

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
                primary: true
            }, {
                property: 'shortCode',
                label: this.$tc('sw-settings-country.detail.columnStateShortCodeLabel'),
                inlineEdit: 'string'
            }];
        }
    }
});

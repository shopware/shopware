import template from './sw-settings-country-state.html.twig';
import './sw-settings-country-state.scss';

const { Component, Mixin } = Shopware;

Component.register('sw-settings-country-state', {
    template,
    flag: 'FEATURE_NEXT_14114',

    inject: [
        'repositoryFactory',
        'acl',
        'feature',
    ],

    mixins: [
        Mixin.getByName('notification'),
    ],

    props: {
        country: {
            type: Object,
            required: true,
        },
        isLoading: {
            type: Boolean,
            required: false,
            default: false,
        },
        countryStateRepository: {
            type: Object,
            required: false,
            default: null,
        },
    },

    data() {
        return {
            deleteButtonDisabled: true,
            term: null,
            currentCountryState: null,
            countryStateLoading: false,
        };
    },

    computed: {
        stateColumns() {
            return this.getStateColumns();
        },
    },

    methods: {
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

        countryStateSelectionChanged(selection, selectionCount) {
            this.deleteButtonDisabled = selectionCount <= 0;
        },

        onSearchCountryState() {
            this.country.states.criteria.setTerm(this.term);
            this.refreshCountryStateList();
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
                    this.refreshCountryStateList();
                });
        },

        onAddCountryState() {
            this.currentCountryState = this.countryStateRepository.create(Shopware.Context.api);
        },

        onSaveCountryState() {
            // dont send requests if we are on local mode(creating a new country)
            if (this.country.isNew()) {
                this.country.states.add(this.currentCountryState);
                this.currentCountryState = null;
                return Promise.resolve();
            }

            return this.countryStateRepository.save(this.currentCountryState).then(() => {
                this.refreshCountryStateList();
                this.currentCountryState = null;
            }).catch(errors => {
                if (this.feature.isActive('FEATURE_NEXT_14114')
                    && errors.response.data.errors[0].code === 'MISSING-SYSTEM-TRANSLATION') {
                    this.createNotificationError({
                        message: this.$tc('sw-country-state-detail.createNewStateError'),
                    });
                }
            });
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

        refreshCountryStateList() {
            this.countryStateLoading = true;

            this.$refs.countryStateGrid.load().then(() => {
                this.countryStateLoading = false;
            });
        },
    },
});

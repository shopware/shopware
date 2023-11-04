/**
 * @package system-settings
 */
import template from './sw-settings-country-state.html.twig';
import './sw-settings-country-state.scss';

const { Mixin } = Shopware;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: [
        'repositoryFactory',
        'acl',
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
            showEmptyState: false,
        };
    },

    computed: {
        stateColumns() {
            return this.getStateColumns();
        },

        countryStates() {
            return this.country.states;
        },
    },

    watch: {
        countryStates() {
            this.checkEmptyState();
        },
    },

    mounted() {
        this.mountedComponent();
    },

    methods: {
        mountedComponent() {
            this.checkEmptyState();
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

            if (this.country.isNew()) {
                countryStateIds.forEach(countryStateId => {
                    this.country.states.remove(countryStateId);
                });

                this.$refs.countryStateGrid.resetSelection();
                return Promise.resolve();
            }

            this.countryStateLoading = true;

            return this.countryStateRepository.syncDeleted(countryStateIds, Shopware.Context.api)
                .then(() => {
                    this.$refs.countryStateGrid.resetSelection();
                    this.refreshCountryStateList();
                }).finally(() => {
                    this.countryStateLoading = false;
                });
        },

        onAddCountryState() {
            this.currentCountryState = this.countryStateRepository.create(Shopware.Context.api);
        },

        onSaveCountryState(countryState) {
            // do not send requests if we are on local mode(creating a new country)
            if (this.country.isNew()) {
                this.country.states.add(countryState);

                return Promise.resolve().then(() => {
                    this.currentCountryState = null;
                });
            }

            return this.countryStateRepository.save(this.currentCountryState).then(() => {
                this.refreshCountryStateList();
            }).catch(errors => {
                if (errors.response.data.errors[0].code === 'MISSING-SYSTEM-TRANSLATION') {
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
                this.currentCountryState = null;
            });
        },

        getCountryStateName(item) {
            return item?.translated?.name || item?.name;
        },

        checkEmptyState() {
            if (this.country.isNew()) {
                this.showEmptyState = this.country.states.length === 0;
                return;
            }

            this.showEmptyState = this.$refs.countryStateGrid.total === 0;
        },
    },
};

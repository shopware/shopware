import EntityValidationService from 'src/app/service/entity-validation.service';
import template from './sw-customer-address-form.html.twig';
import './sw-customer-address-form.scss';

/**
 * @sw-package checkout
 */

const { Defaults, EntityDefinition } = Shopware;
const { Criteria } = Shopware.Data;
const { mapPropertyErrors } = Shopware.Component.getComponentHelper();

const COUNTRY_DEPENDENT_FIELDS = {
    countryStateId: 'forceStateInRegistration',
    zipcode: 'postalCodeRequired',
};

const MANAGED_REQUIRED_FIELDS = [
    'company',
    ...Object.keys(COUNTRY_DEPENDENT_FIELDS),
];

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['repositoryFactory'],

    props: {
        customer: {
            type: Object,
            required: true,
        },

        address: {
            type: Object,
            required: true,
            default() {
                return this.addressRepository.create(this.context);
            },
        },

        disabled: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    data() {
        return {
            country: null,
            states: [],
        };
    },

    computed: {
        addressRepository() {
            return this.repositoryFactory.create(this.customer.addresses.entity, this.customer.addresses.source);
        },

        countryRepository() {
            return this.repositoryFactory.create('country');
        },

        countryStateRepository() {
            return this.repositoryFactory.create('country_state');
        },

        ...mapPropertyErrors('address', [
            'company',
            'department',
            'salutationId',
            'title',
            'firstName',
            'lastName',
            'street',
            'additionalAddressLine1',
            'additionalAddressLine2',
            'zipcode',
            'city',
            'countryId',
            'phoneNumber',
            'countryStateId',
            'salutationId',
            'city',
            'street',
            'zipcode',
            'lastName',
            'firstName',
        ]),

        countryId: {
            get() {
                return this.address.countryId;
            },

            set(countryId) {
                this.address.countryId = countryId;
            },
        },

        countryCriteria() {
            const criteria = new Criteria(1, 25);
            criteria.addSorting(Criteria.sort('position', 'ASC', true)).addSorting(Criteria.sort('name', 'ASC'));
            return criteria;
        },

        stateCriteria() {
            if (!this.countryId) {
                return null;
            }

            const criteria = new Criteria(1, 25);
            criteria
                .addFilter(Criteria.equals('countryId', this.countryId))
                .addSorting(Criteria.sort('position', 'ASC', true))
                .addSorting(Criteria.sort('name', 'ASC'));
            return criteria;
        },

        salutationCriteria() {
            const criteria = new Criteria(1, 25);

            criteria.addFilter(
                Criteria.not('or', [
                    Criteria.equals('id', Defaults.defaultSalutationId),
                ]),
            );

            return criteria;
        },

        hasStates() {
            return this.states.length > 0;
        },

        isBusinessAccountType() {
            return this.customer?.accountType === Shopware.Constants.CUSTOMER.ACCOUNT_TYPE_BUSINESS;
        },
    },

    watch: {
        countryId: {
            immediate: true,
            handler(newId, oldId) {
                if (typeof oldId !== 'undefined') {
                    this.address.countryStateId = null;
                }

                if (!this.countryId) {
                    this.country = null;
                    return Promise.resolve();
                }

                return this.countryRepository.get(this.countryId).then((country) => {
                    this.country = country;

                    this.address.country = this.country;
                    this.getCountryStates();
                });
            },
        },

        isBusinessAccountType: {
            immediate: true,
            handler(newVal) {
                this.setFieldRequired('company', newVal);
            },
        },

        'address.company'(newVal) {
            if (!newVal || !this.customer.isNew()) {
                return;
            }

            this.customer.company = newVal;
        },

        country: {
            immediate: true,
            handler(country, previousCountry) {
                Object.entries(COUNTRY_DEPENDENT_FIELDS).forEach(
                    ([
                        field,
                        countryProperty,
                    ]) => {
                        const isRequired = Boolean(country?.[countryProperty]);

                        this.setFieldRequired(field, isRequired);

                        if (!isRequired && previousCountry !== undefined) {
                            this.removeRequiredFieldError(field);
                        }
                    },
                );
            },
        },
    },

    beforeUnmount() {
        MANAGED_REQUIRED_FIELDS.forEach((field) => this.setFieldRequired(field, false));
    },

    methods: {
        setFieldRequired(field, isRequired) {
            const property = EntityDefinition.get(this.address.getEntityName()).properties[field];

            if (!property?.flags) {
                return;
            }

            property.flags.required = Boolean(isRequired);
        },

        removeRequiredFieldError(field) {
            const entityName = this.address.getEntityName();
            const errorStore = Shopware.Store.get('error');
            const error = errorStore.getApiErrorFromPath(entityName, this.address.id, [field]);

            if (error?.code !== EntityValidationService.ERROR_CODE_REQUIRED) {
                return;
            }

            errorStore.removeApiError(`${entityName}.${this.address.id}.${field}`);
        },

        getCountryStates() {
            if (!this.country) {
                return Promise.resolve();
            }

            return this.countryStateRepository.search(this.stateCriteria).then((response) => {
                this.states = response;
            });
        },
    },
};

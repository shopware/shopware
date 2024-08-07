import template from './sw-customer-address-form.html.twig';
import './sw-customer-address-form.scss';
import CUSTOMER from '../../constant/sw-customer.constant';

/**
 * @package checkout
 */

const { Defaults, EntityDefinition } = Shopware;
const { Criteria } = Shopware.Data;
const { mapPropertyErrors } = Shopware.Component.getComponentHelper();

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
            return this.repositoryFactory.create(
                this.customer.addresses.entity,
                this.customer.addresses.source,
            );
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
            'vatId',
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
            criteria.addSorting(Criteria.sort('position', 'ASC', true))
                .addSorting(Criteria.sort('name', 'ASC'));
            return criteria;
        },

        stateCriteria() {
            if (!this.countryId) {
                return null;
            }

            const criteria = new Criteria(1, 25);
            criteria.addFilter(Criteria.equals('countryId', this.countryId))
                .addSorting(Criteria.sort('position', 'ASC', true))
                .addSorting(Criteria.sort('name', 'ASC'));
            return criteria;
        },

        salutationCriteria() {
            const criteria = new Criteria(1, 25);

            criteria.addFilter(Criteria.not('or', [
                Criteria.equals('id', Defaults.defaultSalutationId),
            ]));

            return criteria;
        },

        hasStates() {
            return this.states.length > 0;
        },

        isBusinessAccountType() {
            return this.customer?.accountType === CUSTOMER.ACCOUNT_TYPE_BUSINESS;
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

        'address.company'(newVal) {
            if (!newVal) {
                return;
            }

            this.customer.company = newVal;
        },

        'country.forceStateInRegistration'(newVal) {
            if (!newVal) {
                Shopware.State.dispatch(
                    'error/removeApiError',
                    {
                        expression: `${this.address.getEntityName()}.${this.address.id}.countryStateId`,
                    },
                );
            }

            const definition = EntityDefinition.get(this.address.getEntityName());

            definition.properties.countryStateId.flags.required = newVal;
        },

        'country.postalCodeRequired'(newVal) {
            if (!newVal) {
                Shopware.State.dispatch(
                    'error/removeApiError',
                    {
                        expression: `${this.address.getEntityName()}.${this.address.id}.zipcode`,
                    },
                );
            }

            const definition = EntityDefinition.get(this.address.getEntityName());

            definition.properties.zipcode.flags.required = newVal;
        },
    },

    methods: {
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

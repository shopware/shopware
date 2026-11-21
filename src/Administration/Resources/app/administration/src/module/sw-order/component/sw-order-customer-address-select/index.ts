import template from './sw-order-customer-address-select.html.twig';
import './sw-order-customer-address-select.scss';
import type CriteriaType from '../../../../core/data/criteria.data';
import type Repository from '../../../../core/data/repository.data';

/**
 * @sw-package checkout
 */

const { Component } = Shopware;
const { Criteria } = Shopware.Data;

const DEPRECATION_MESSAGE_SUFFIX = 'The address select loads the addresses itself, page by page.';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default Component.wrapComponentConfig({
    template,

    inject: ['repositoryFactory', 'feature'],

    emits: ['update:value'],

    props: {
        customer: {
            type: Object as PropType<Entity<'customer'> | null>,
            required: false,
            default: null,
        },

        value: {
            type: String as PropType<string>,
            required: true,
        },

        sameAddressLabel: {
            type: String as PropType<string>,
            required: false,
            default: '',
        },

        sameAddressValue: {
            type: String as PropType<string>,
            required: true,
        },

        disabled: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    data(): {
        /**
         * @deprecated tag:v6.8.0 - Will be removed, the address select loads the addresses itself.
         */
        customerAddresses: Entity<'customer_address'>[];
        /**
         * @deprecated tag:v6.8.0 - Will be removed, the address select shows its own loading state.
         */
        isLoading: boolean;
        /**
         * @deprecated tag:v6.8.0 - Will be removed, the address select keeps its own search term.
         */
        addressSearchTerm: string;
    } {
        return {
            customerAddresses: [],
            isLoading: false,
            addressSearchTerm: '',
        };
    },

    computed: {
        addressId: {
            get(): string {
                return this.value;
            },
            set(newValue: string | null): void {
                if (newValue === null) {
                    return;
                }

                this.$emit('update:value', newValue);
            },
        },

        isSameAddress(): boolean {
            return this.value === this.sameAddressValue;
        },

        customerAddressCriteria(): CriteriaType {
            // A customer can have thousands of addresses, the select loads them page by page
            const criteria = new Criteria(1, 25);
            criteria.addFilter(Criteria.equals('customerId', this.customer?.id ?? null));
            criteria.addAssociation('salutation');
            criteria.addAssociation('country');
            criteria.addAssociation('countryState');

            // A fixed order keeps the pages from overlapping or leaving out addresses with the same search score.
            // The DAL ignores the _score sorting without a search term.
            criteria.addSorting(Criteria.sort('_score', 'DESC'));
            criteria.addSorting(Criteria.sort('createdAt', 'ASC'));
            criteria.addSorting(Criteria.sort('id', 'ASC'));

            return criteria;
        },

        /**
         * @deprecated tag:v6.8.0 - Will be removed without replacement.
         */
        addressRepository(): Repository<'customer_address'> {
            Shopware.Feature.triggerDeprecationOrThrow(
                'V6_8_0_0',
                `sw-order-customer-address-select.addressRepository is deprecated. ${DEPRECATION_MESSAGE_SUFFIX}`,
            );

            return this.repositoryFactory.create(
                this.customer?.addresses?.entity ?? 'customer_address',
                this.customer?.addresses?.source,
            );
        },

        /**
         * @deprecated tag:v6.8.0 - Will be removed. Use customerAddressCriteria instead.
         */
        addressCriteria(): CriteriaType {
            Shopware.Feature.triggerDeprecationOrThrow(
                'V6_8_0_0',
                'sw-order-customer-address-select.addressCriteria is deprecated. Use customerAddressCriteria instead.',
            );

            const criteria = new Criteria(1, null);
            criteria.addAssociation('salutation');
            criteria.addAssociation('country');
            criteria.addAssociation('countryState');

            if (this.addressSearchTerm) {
                criteria.setTerm(this.addressSearchTerm);
            }

            return criteria;
        },
    },

    watch: {
        'customer.id': {
            handler(): void {
                if (!this.feature.isActive('V6_8_0_0')) {
                    Shopware.Feature.silence('V6_8_0_0', () => {
                        void this.getCustomerAddresses();
                    });
                }
            },
        },

        value(): void {
            // The select loads the selected address with these criteria, a term or page left from the result list would miss it
            this.customerAddressCriteria.setPage(1);
            this.customerAddressCriteria.setTerm('');
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent(): void {
            // Without the flag, the template keeps the old select, which needs the loaded addresses
            if (!this.feature.isActive('V6_8_0_0')) {
                Shopware.Feature.silence('V6_8_0_0', () => {
                    void this.getCustomerAddresses();
                });
            }
        },

        getSelectionLabel(item: Entity<'customer_address'>): string {
            if (this.isSameAddress && this.sameAddressLabel) {
                return this.sameAddressLabel;
            }

            return this.getCustomerAddress(item);
        },

        getCustomerAddress(address: Entity<'customer_address'>): string {
            if (!address) return '';

            const result: Array<string> = [];

            const properties = [
                'street',
                'zipcode',
                'city',
                'countryState',
                'country',
            ] as const;

            properties.forEach((property) => {
                const adressProperty = address[property];

                if (!adressProperty) {
                    return;
                }

                if (property === 'countryState' || property === 'country') {
                    const name = address[property]?.translated?.name;

                    if (name) {
                        result.push(name);
                    }

                    return;
                }

                result.push(<string>address[property]);
            });

            return result.join(', ');
        },

        /**
         * @deprecated tag:v6.8.0 - Will be removed without replacement.
         */
        getCustomerAddresses(): Promise<void> {
            Shopware.Feature.triggerDeprecationOrThrow(
                'V6_8_0_0',
                `sw-order-customer-address-select.getCustomerAddresses() is deprecated. ${DEPRECATION_MESSAGE_SUFFIX}`,
            );

            this.isLoading = true;

            // Get the latest addresses from customer's db
            return this.addressRepository
                .search(this.addressCriteria)
                .then((addresses: EntityCollection<'customer_address'>): void => {
                    this.customerAddresses = Shopware.Feature.silence('V6_8_0_0', () =>
                        this.getDisplayedCustomerAddresses(addresses),
                    );
                })
                .finally(() => {
                    this.isLoading = false;
                });
        },

        /**
         * @deprecated tag:v6.8.0 - Will be removed without replacement.
         */
        // eslint-disable-next-line sw-deprecation-rules/require-deprecation-guard -- the select without the v6.8.0.0 flag calls it on every search
        searchAddress(searchTerm: string): Promise<void> {
            this.isLoading = true;

            this.addressSearchTerm = searchTerm;

            const [addressRepository, addressCriteria] = Shopware.Feature.silence(
                'V6_8_0_0',
                () => [this.addressRepository, this.addressCriteria] as const,
            );

            return addressRepository
                .search(addressCriteria)
                .then((addresses: EntityCollection<'customer_address'>) => {
                    this.customerAddresses = Shopware.Feature.silence('V6_8_0_0', () =>
                        this.getDisplayedCustomerAddresses(addresses),
                    );
                })
                .finally(() => {
                    this.isLoading = false;
                });
        },

        /**
         * @deprecated tag:v6.8.0 - Will be removed without replacement.
         */
        // eslint-disable-next-line sw-deprecation-rules/require-deprecation-guard -- the select without the v6.8.0.0 flag calls it on every search
        searchAddressResults() {
            return this.customerAddresses;
        },

        /**
         * @deprecated tag:v6.8.0 - Will be removed without replacement.
         */
        getDisplayedCustomerAddresses(addresses: EntityCollection<'customer_address'>): Entity<'customer_address'>[] {
            Shopware.Feature.triggerDeprecationOrThrow(
                'V6_8_0_0',
                `sw-order-customer-address-select.getDisplayedCustomerAddresses() is deprecated. ${DEPRECATION_MESSAGE_SUFFIX}`,
            );

            const customerAddresses = [...addresses];

            if (!this.addressId || customerAddresses.some((address) => address.id === this.addressId)) {
                return customerAddresses;
            }

            const selectedAddress = this.customerAddresses.find((address) => address.id === this.addressId);

            if (selectedAddress) {
                customerAddresses.unshift(selectedAddress);
            }

            return customerAddresses;
        },
    },
});

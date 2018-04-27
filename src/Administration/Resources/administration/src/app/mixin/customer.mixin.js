import utils from 'src/core/service/util.service';
import { Mixin, Entity } from 'src/core/shopware';

/**
 * @module app/mixin/customer
 */
Mixin.register('customer', {
    data() {
        return {
            customerId: null,
            isLoading: false,
            isLoaded: false,
            customer: Entity.getRawEntityObject('customer', true)
        };
    },

    /**
     * If the create route is called we pre-generate an ID for the new customer.
     * This enables all sub-components to work with the new generated customer by using this mixin.
     *
     * @param to
     * @param from
     * @param next
     */
    beforeRouteEnter(to, from, next) {
        if (to.name.includes('sw.customer.create') && !to.params.id) {
            to.params.id = utils.createId();
        }

        next();
    },

    computed: {
        /**
         * The object you should work with is the internal customer binding.
         * Although you can access the state object directly via this computed property.
         * Be careful to not directly change this object without using mutations.
         * We want to track all changes via mutations so we use the state in strict mode.
         *
         * @returns {*}
         */
        customerState() {
            return this.$store.state.customer.draft[this.customerId];
        },

        requiredCustomerFields() {
            return Entity.getRequiredProperties('customer');
        }
    },

    watch: {
        /**
         * The watcher keeps track of the local data object and
         * updates the state object accordingly with a correct mutation.
         */
        customer: {
            deep: true,
            handler() {
                this.commitCustomer();
            }
        }
    },

    mounted() {
        if (this.$route.name.includes('sw.customer.create')) {
            this.createEmptyCustomer(this.customerId);
        } else {
            this.getCustomerById(this.customerId);
        }
    },

    methods: {
        getCustomerById(customerId) {
            this.isLoading = true;

            return this.$store.dispatch('customer/getCustomerById', customerId).then(() => {
                this.isLoaded = true;
                this.isLoading = false;
                this.customer = this.customerState;

                return this.customer;
            });
        },

        createEmptyCustomer(customerId) {
            return this.$store.dispatch('customer/createEmptyCustomer', customerId).then(() => {
                this.isLoaded = true;
                this.customer = this.customerState;
            });
        },

        saveCustomer() {
            this.isLoading = true;

            return this.$store.dispatch('customer/saveCustomer', this.customer).then((customer) => {
                this.isLoading = false;
                if (this.$route.name.includes('sw.customer.create')) {
                    this.$router.push({ name: 'sw.customer.detail', params: { id: customer.id } });
                }

                return Promise.resolve();
            }).catch((exception) => {
                this.isLoading = false;
                this.customer = Object.assign({}, this.customer);

                return Promise.reject(exception);
            });
        },

        commitCustomer: utils.throttle(function throttledCommitCustomer() {
            return this.$store.commit('customer/setCustomer', this.customer);
        }, 500)
    }
});

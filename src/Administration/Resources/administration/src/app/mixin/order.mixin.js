import utils from 'src/core/service/util.service';
import { Mixin, Entity } from 'src/core/shopware';

/**
 * @module app/mixin/order
 */
Mixin.register('order', {
    /**
     * If the create route is called we pre-generate an ID for the new order.
     * This enables all sub-components to work with the new generated order by using this mixin.
     *
     * @param to
     * @param from
     * @param next
     */
    beforeRouteEnter(to, from, next) {
        if (to.name.includes('sw.order.create') && !to.params.id) {
            to.params.id = utils.createId();
        }

        next();
    },

    data() {
        return {
            orderId: null,
            isLoading: false,
            isLoaded: false,
            order: Entity.getRawEntityObject('order', true)
        };
    },

    computed: {
        /**
         * The object you should work with is the internal order binding.
         * Although you can access the state object directly via this computed property.
         * Be careful to not directly change this object without using mutations.
         * We want to track all changes via mutations so we use the state in strict mode.
         *
         * @returns {*}
         */
        orderState() {
            return this.$store.state.order.draft[this.orderId];
        },

        requiredOrderFields() {
            return Entity.getRequiredProperties('order');
        }
    },

    watch: {
        /**
         * The watcher keeps track of the local data object and
         * updates the state object accordingly with a correct mutation.
         */
        order: {
            deep: true,
            handler() {
                this.commitOrder();
            }
        }
    },

    mounted() {
        if (this.$route.name.includes('sw.order.create')) {
            this.createEmptyOrder(this.orderId);
        } else {
            this.getOrderById(this.orderId);
        }
    },

    methods: {
        getOrderById(orderId) {
            this.isLoading = true;

            return this.$store.dispatch('order/getOrderById', orderId).then(() => {
                this.isLoaded = true;
                this.isLoading = false;
                this.order = this.orderState;

                return this.order;
            });
        },

        createEmptyOrder(orderId) {
            return this.$store.dispatch('order/createEmptyOrder', orderId).then(() => {
                this.isLoaded = true;
                this.order = this.orderState;
            });
        },

        saveOrder() {
            this.isLoading = true;

            return this.$store.dispatch('order/saveOrder', this.order).then((order) => {
                this.isLoading = false;

                if (this.$route.name.includes('sw.order.create')) {
                    this.$router.push({ name: 'sw.order.detail', params: { id: order.id } });
                }
            }).catch(() => {
                this.isLoading = false;
            });
        },

        commitOrder: utils.debounce(function debouncedCommitOrder() {
            return this.$store.commit('order/setOrder', this.order);
        }, 500)
    }
});

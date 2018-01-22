import { Mixin, Entity } from 'src/core/shopware';

/**
 * @module app/mixin/product
 */
Mixin.register('product', {
    data() {
        return {
            productId: null,
            isLoading: false,
            isLoaded: false,
            product: Entity.getRawEntityObject('product', true)
        };
    },

    /**
     * If the create route is called we pre-generate an ID for the new product.
     * This enables all sub-components to work with the new generated product by using this mixin.
     *
     * @param to
     * @param from
     * @param next
     */
    beforeRouteEnter(to, from, next) {
        if (to.name === 'sw.product.create' && !to.params.id) {
            to.params.id = Shopware.Utils.createId();
        }

        next();
    },

    computed: {
        /**
         * The object you should work with is the internal product binding.
         * Although you can access the state object directly via this computed property.
         * Be careful to not directly change this object without using mutations.
         * We want to track all changes via mutations so we use the state in strict mode.
         *
         * @returns {*}
         */
        productState() {
            return this.$store.state.product.draft[this.productId];
        },

        requiredProductFields() {
            return Entity.getRequiredProperties('product');
        }
    },

    watch: {
        /**
         * The watcher keeps track of the local data object and
         * updates the state object accordingly with a correct mutation.
         */
        product: {
            deep: true,
            handler() {
                Shopware.Utils.debounce(this.commitProduct, 500);
            }
        }
    },

    mounted() {
        if (this.$route.name === 'sw.product.create') {
            this.createEmptyProduct(this.productId);
        } else {
            this.getProductById(this.productId);
        }
    },

    methods: {
        getProductById(productId) {
            this.isLoading = true;

            return this.$store.dispatch('product/getProductById', productId).then(() => {
                this.isLoaded = true;
                this.isLoading = false;
                this.product = this.productState;

                return this.product;
            });
        },

        createEmptyProduct(productId) {
            return this.$store.dispatch('product/createEmptyProduct', productId).then(() => {
                this.product = this.productState;
                this.isLoaded = true;
            });
        },

        saveProduct() {
            this.isLoading = true;

            return this.$store.dispatch('product/saveProduct', this.product).then(() => {
                this.isLoading = false;
            });
        },

        commitProduct() {
            return this.$store.commit('product/setProduct', this.product);
        }
    }
});

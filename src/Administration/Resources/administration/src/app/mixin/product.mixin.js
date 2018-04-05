import utils from 'src/core/service/util.service';
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
        if (to.name.includes('sw.product.create') && !to.params.id) {
            to.params.id = utils.createId();
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
                this.commitProduct();
            }
        }
    },

    mounted() {
        if (this.$route.name.includes('sw.product.create')) {
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
                this.isLoaded = true;
                this.product = this.productState;
            });
        },

        saveProduct() {
            this.isLoading = true;

            return this.$store.dispatch('product/saveProduct', this.product).then((product) => {
                this.isLoading = false;

                if (this.$route.name.includes('sw.product.create')) {
                    this.$router.push({ name: 'sw.product.detail', params: { id: product.id } });
                }
            }).catch(() => {
                this.isLoading = false;
            });
        },

        commitProduct: utils.throttle(function throttledCommitProduct() {
            return this.$store.commit('product/setProduct', this.product);
        }, 500)
    }
});

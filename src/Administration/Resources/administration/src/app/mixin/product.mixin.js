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
            product: Entity.getRawEntityObject('product', true),
            productErrors: {}
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
                this.isLoaded = true;
                this.product = this.productState;
            });
        },

        saveProduct() {
            this.isLoading = true;

            return this.$store.dispatch('product/saveProduct', this.product).then((product) => {
                this.isLoading = false;

                if (this.$route.name === 'sw.product.create') {
                    this.$router.push({ name: 'sw.product.detail', params: { id: product.id } });
                }
            }).catch((exception) => {
                this.isLoading = false;

                if (exception.response.data && exception.response.data.errors) {
                    this.handleProductErrors(exception.response.data.errors);
                }

                return exception;
            });
        },

        commitProduct: utils.throttle(function throttledCommitProduct() {
            return this.$store.commit('product/setProduct', this.product);
        }, 500),

        handleProductErrors(errors) {
            errors.forEach((error, index) => {
                if (error.source && error.source.pointer) {
                    error.propertyDepth = error.source.pointer.split('/');

                    error.propertyPath = `product${error.propertyDepth.join('.')}`;

                    error.unwatch = this.$watch(error.propertyPath, () => {
                        this.resetError(error);
                    });

                    error.propertyDepth.reduce((obj, key, i) => {
                        if (!key.length || key.length <= 0) {
                            return obj;
                        }

                        obj[key] = (i === error.propertyDepth.length - 1) ? error : {};

                        return obj[key];
                    }, this.productErrors);
                } else {
                    this.productErrors[index] = error;
                }
            });
        },

        resetError(error) {
            if (error.unwatch && typeof error.unwatch === 'function') {
                error.unwatch();
            }

            error.propertyDepth.reduce((obj, key, index) => {
                if (index === error.propertyDepth.length - 1) {
                    delete obj[key];
                }

                return obj;
            }, this.productErrors);
        }
    }
});

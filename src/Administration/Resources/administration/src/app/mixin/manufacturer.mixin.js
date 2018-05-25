import utils from 'src/core/service/util.service';
import { Mixin, Entity } from 'src/core/shopware';

/**
 * @module app/mixin/manufacturer
 */
Mixin.register('manufacturer', {
    data() {
        return {
            manufacturerId: null,
            isLoading: false,
            isLoaded: false,
            manufacturer: Entity.getRawEntityObject('product_manufacturer', true)
        };
    },

    /**
     * If the create route is called we pre-generate an ID for the new product.
     * This enables all sub-components to work with the new generated product by using this mixin.
     *
     * @memberOf module:app/mixin/manufacturer
     * @param {Object} to
     * @param {Object} from
     * @param {Function} next
     */
    beforeRouteEnter(to, from, next) {
        if (to.name.includes('sw.manufacturer.create') && !to.params.id) {
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
        manufacturerState() {
            return this.$store.state.manufacturer.draft[this.manufacturerId];
        },

        requiredManufacturerFields() {
            return Entity.getRequiredProperties('product_manufacturer');
        }
    },

    watch: {
        /**
         * The watcher keeps track of the local data object and
         * updates the state object accordingly with a correct mutation.
         */
        manufacturer: {
            deep: true,
            handler() {
                this.commitManufacturer();
            }
        }
    },

    mounted() {
        if (this.$route.name.includes('sw.manufacturer.create')) {
            this.createEmptyManufacturer(this.manufacturerId);
        } else {
            this.getManufacturerById(this.manufacturerId);
        }
    },

    methods: {
        /**
         * Creates an empty manufacturer when the route contains the string `sw.manufacturer.create`
         *
         * @param {Number} manufacturerId
         * @returns {Promise<any>}
         */
        createEmptyManufacturer(manufacturerId) {
            return this.$store.dispatch('manufacturer/createEmptyManufacturer', manufacturerId).then(() => {
                this.isLoaded = true;
                this.manufacturer = this.manufacturerState;
            });
        },

        /**
         * Returns a specific manufacturer by id from the API.
         *
         * @param {Number} manufacturerId
         * @returns {Promise<any>}
         */
        getManufacturerById(manufacturerId) {
            this.isLoading = true;

            return this.$store.dispatch('manufacturer/getManufacturerById', manufacturerId).then(() => {
                this.isLoaded = true;
                this.isLoading = false;
                this.manufacturer = this.manufacturerState;

                return this.manufacturer;
            });
        },

        saveManufacturer() {
            this.isLoading = true;

            return this.$store.dispatch('manufacturer/saveManufacturer', this.manufacturer).then((manufacturer) => {
                this.isLoading = false;

                if (this.$route.name.includes('sw.manufacturer.create')) {
                    this.$router.push({ name: 'sw.manufacturer.detail', params: { id: manufacturer.id } });
                }
            }).catch(() => {
                this.isLoading = false;
            });
        },

        commitManufacturer: utils.throttle(function throttledCommitManufacturer() {
            return this.$store.commit('manufacturer/setManufacturer', this.manufacturer);
        }, 500)
    }
});

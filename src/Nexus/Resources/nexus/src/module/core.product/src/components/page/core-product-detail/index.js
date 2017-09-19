import utils from 'src/core/service/util.service';
import template from './core-product-detail.html.twig';
import './core-product-detail.less';

export default Shopware.ComponentFactory.register('core-product-detail', {
    inject: ['productService', 'productManufacturerService'],

    data() {
        return {
            isWorking: false,
            product: {
                manufacturer: {},
                attribute: {},
                mainDetail: {},
                extensions: {
                    nexus: {
                        voteAverage: 10.5,
                        listingPrice: 1.50
                    }
                }
            },
            notModifiedProduct: {}
        };
    },

    computed: {
        productManufacturer() {
            return this.productManufacturerService;
        }
    },

    created() {
        this.getData();
    },

    watch: {
        $route: 'getData'
    },

    methods: {
        getData() {
            const uuid = this.$route.params.uuid;

            this.isWorking = true;
            this.productService.readByUuid(uuid).then((response) => {
                this.notModifiedProduct = { ...response.data };
                this.product = response.data;
                this.isWorking = false;
            });
        },

        onSaveForm() {
            const uuid = this.$route.params.uuid;
            const changeSet = utils.compareObjects(this.notModifiedProduct, this.product);

            this.isWorking = true;
            this.productService.updateByUuid(uuid, changeSet).then(() => {
                this.isWorking = false;
            });
        }
    },

    template
});

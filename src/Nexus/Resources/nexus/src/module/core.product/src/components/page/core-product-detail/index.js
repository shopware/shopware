import template from './core-product-detail.html.twig';
import './core-product-detail.less';

export default Shopware.ComponentFactory.register('core-product-detail', {
    inject: ['productRepository', 'categoryService', 'productManufacturerService', 'taxService'],

    data() {
        return {
            isWorking: false,
            product: {
                manufacturer: {},
                attribute: {},
                mainDetail: {},
                categories: [],
                extensions: {
                    nexus: {
                        voteAverage: 10.5,
                        listingPrice: 1.50
                    }
                }
            },
            taxRates: [],
            manufacturers: [],
            notModifiedProduct: {}
        };
    },

    computed: {
        categoryService() {
            return this.categoryService;
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
            this.getProductData();
            this.getManufacturerData();
            this.getTaxData();
        },

        getProductData() {
            const uuid = this.$route.params.uuid;

            this.isWorking = true;

            this.productRepository.getByUuid(uuid).then((productProxy) => {
                this.productProxy = productProxy;
                this.product = productProxy.data;
                this.isWorking = false;
            });
        },

        getManufacturerData() {
            this.productManufacturerService.readAll().then((response) => {
                this.manufacturers = response.data;
            });
        },

        getTaxData() {
            this.taxService.readAll().then((response) => {
                this.taxRates = response.data;
            });
        },

        onSave() {
            const uuid = this.$route.params.uuid;

            this.isWorking = true;
            this.productRepository.updateByUuid(uuid, this.productProxy).then(() => {
                this.isWorking = false;
            });
        }
    },

    template
});

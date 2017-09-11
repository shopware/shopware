import template from 'src/module/core.product/src/core-product-detail/core-product-detail.html.twig';
import utils from 'src/core/service/util.service';
import 'src/module/core.product/src/core-product-detail/core-product-detail.less';

export default Shopware.ComponentFactory.register('core-product-detail', {
    inject: ['productService'],

    data() {
        return {
            isWorking: false,
            product: {
                manufacturer: {},
                mainDetail: {}
            },
            notModifiedProduct: {}
        };
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
                this.notModifiedProduct = JSON.parse(JSON.stringify(response.data));
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

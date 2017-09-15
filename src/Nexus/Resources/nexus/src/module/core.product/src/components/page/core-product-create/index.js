import utils from 'src/core/service/util.service';
import template from './core-product-create.html.twig';
import './core-product-create.less';

export default Shopware.ComponentFactory.register('core-product-create', {
    inject: ['productService'],

    data() {
        return {
            isWorking: false,
            product: {
                taxUuid: 'SWAG-TAX-UUID-1',
                mainDetailUuid: '',
                manufacturerUuid: 'SWAG-PRODUCT-MANUFACTURER-UUID-2',
                manufacturer: {
                    name: '',
                    descriptionLong: ''
                },
                details: {
                    mainDetailUuid: '',
                    name: ''
                }
            }
        };
    },

    created() {
        const productUuid = utils.createUuid();

        this.product.mainDetailUuid = productUuid;
        this.product.details.mainDetailUuid = productUuid;
    },

    methods: {
        onSaveForm() {
            console.log(this.product);
            // this.isWorking = true;
            /* this.productService.updateByUuid(uuid, changeSet).then(() => {
                this.isWorking = false;
            }); */
        }
    },

    template
});

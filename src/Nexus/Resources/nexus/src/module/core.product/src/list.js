import 'module/core.product/src/list/list.less';
import template from 'module/core.product/src/list/list.html';
import PaginationMixin from 'src/app/component/mixin/pagination.mixin';

export default Shopware.ComponentFactory.register('core-product-list', {
    inject: ['productService', 'eventEmitter'],
    mixins: [PaginationMixin],

    props: {
        title: {
            type: String,
            required: false,
            default: 'Paginated product list'
        }
    },

    data() {
        return {
            limit: 25,
            pageNum: 1,
            isWorking: false,
            productList: [],
            total: 0
        };
    },

    created() {
        this.getData();
    },

    mounted() {
        this.eventEmitter.on('save-editing', this.saveRowEditing.bind(this));
    },

    unmounted() {
        this.eventEmitter.off('save-editing');
    },

    methods: {
        getData() {
            this.isWorking = true;
            this.productService
                .readAllProductsAsPaginatedList(this.limit, this.offset)
                .then((productList) => {
                    this.isWorking = false;
                    this.productList = productList.products;
                    this.total = productList.totalProducts;
                });
        },

        saveRowEditing(opts) {
            this.isWorking = true;
            this.productService.updateProductById(opts.id, opts.items).then(() => {
                this.productList.forEach((item, index) => {
                    if (item.id === opts.id) {
                        this.productList[index] = Object.assign({}, item, opts.items);
                    }
                });
                this.isWorking = false;
            });
        }
    },
    template
});

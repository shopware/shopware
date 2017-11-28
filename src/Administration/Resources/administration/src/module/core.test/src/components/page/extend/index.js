import template from './extend.twig';
import './extend.less';

export default Shopware.Component.extend('core-test-extend', 'core-product-list', {

    data() {
        return {
            extensionTitle: 'This is an extension of the core-product-list Component'
        };
    },

    created() {
        this.myMethod();
        this.myMethodTwo();
    },

    methods: {
        myMethod() {
            console.log('myMethod Extension', this.extensionTitle);
        },

        myMethodTwo() {
            console.log('myMethodTwo Extension');

            this.$super.handlePagination(0, 50);
        }
    },

    template
});

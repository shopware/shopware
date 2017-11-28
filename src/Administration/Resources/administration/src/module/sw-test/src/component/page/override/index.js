import template from './override.twig';

export default Shopware.Component.override('sw-product-list', {

    data() {
        return {
            testColumn: 'This is custom data'
        };
    },

    template
});

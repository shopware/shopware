import template from './override.twig';

export default Shopware.Component.override('core-product-list', {

    data() {
        return {
            testColumn: 'This is custom data'
        };
    },

    template
});

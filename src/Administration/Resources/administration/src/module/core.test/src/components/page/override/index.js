import template from './override.twig';

export default Shopware.ComponentFactory.override('core-product-list', {

    data() {
        return {
            testColumn: 'This is custom data'
        };
    },

    template
});

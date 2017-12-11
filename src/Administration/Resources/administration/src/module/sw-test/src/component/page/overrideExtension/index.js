import template from './override_extension.twig';

Shopware.Component.override('sw-test-extend', {

    data() {
        return {
            overrideData: 'This is an override of an extension'
        };
    },

    methods: {
        myMethod() {
            this.$super.myMethod();

            console.log('myMethod Override', this.overrideData);
        }
    },

    template
});

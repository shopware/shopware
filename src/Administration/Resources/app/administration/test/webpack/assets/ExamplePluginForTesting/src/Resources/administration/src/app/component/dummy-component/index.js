const { Component } = Shopware;
const utils = Shopware.Utils;

Component.register('dummy-component', {
    data() {
        return {};
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            const md5Value = utils.format.md5('Lorem Ipsum');
            const camelCaseSample = utils.string.camelCase('This is a camel case example.');

            console.log(md5Value);
            console.log(camelCaseSample);
        },
    },
});

/**
 * @sw-package framework
 */

const utils = Shopware.Utils;

export default {
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

            // eslint-disable-next-line no-console
            console.log(md5Value);
            // eslint-disable-next-line no-console
            console.log(camelCaseSample);
        },
    },
};

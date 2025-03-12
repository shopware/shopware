import { Mixin } from 'src/core/shopware';
import AgeVerification from './sw-cms-el-form-age-verification';

export default {
    template,

    mixins: [
        Mixin.getByName('cms-element'),
    ],

    components: {
        'sw-cms-el-form-age-verification': AgeVerification
    },

    computed: {
        selectedForm() {
            return this.element.config.type.value;
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.initElementConfig('age-verification');
        },
    },
};

import { VueEditor } from 'vue2-editor';
import vSelect from 'vue-select';
import { Component } from 'src/core/shopware';
import template from './sw-product-basic-form.html.twig';
import './sw-product-basic-form.less';

Component.register('sw-product-basic-form', {
    template,

    components: {
        VueEditor,
        'v-select': vSelect
    },

    props: {
        product: {
            type: Object,
            required: true,
            default: {}
        },
        manufacturers: {
            type: Array,
            required: true,
            default() {
                return [];
            }
        }
    }
});

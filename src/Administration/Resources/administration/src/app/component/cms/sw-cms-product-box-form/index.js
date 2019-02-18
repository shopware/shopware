import { State } from 'src/core/shopware';
import template from './sw-cms-product-box-form.html.twig';

export default {
    name: 'sw-cms-product-box-form',
    template,

    props: {
        config: {
            type: Object
        },
        cmsSlot: {
            type: Object, // requires a block slot
            required: true
        }
    },

    computed: {
        productStore() {
            return State.getStore('product');
        }
    }
};

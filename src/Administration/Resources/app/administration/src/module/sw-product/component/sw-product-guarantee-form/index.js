/*
 * @sw-package inventory
 */

import template from './sw-product-guarantee-form.html.twig';
import './sw-product-guarantee-form.scss';

const { mapPropertyErrors, mapState } = Shopware.Component.getComponentHelper();

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    compatConfig: Shopware.compatConfig,

    props: {
        allowEdit: {
            type: Boolean,
            required: false,
            // eslint-disable-next-line vue/no-boolean-default
            default: true,
        },
    },

    computed: {
        ...mapState('swProductDetail', [
            'product',
            'parentProduct',
        ]),

        ...mapPropertyErrors('product', [
            'guaranteeMonths',
            'guaranteeConfirmed',
        ]),
    },
};

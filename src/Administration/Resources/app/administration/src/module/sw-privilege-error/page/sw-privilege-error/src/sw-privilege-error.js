/**
 * @sw-package framework
 */

import template from './sw-privilege-error.html.twig';
import './sw-privilege-error.scss';
import { getAssetFilter } from '../dependencies';

/**
 * @public
 */
export default {
    template,

    metaInfo() {
        return {
            title: this.$createTitle(this.identifier),
        };
    },

    computed: {
        assetFilter() {
            return getAssetFilter();
        },
    },

    methods: {
        routerGoBack() {
            this.$router.go(-1);
        },
    },
};

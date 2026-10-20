import template from './sw-wrap-config.html.twig';
import './sw-wrap-config.scss';

/**
 * @sw-package framework
 */

const { Component } = Shopware;
const { Criteria } = Shopware.Data;

export default Component.wrapComponentConfig({
    template,

    inject: ['repositoryFactory'],

    computed: {
        criteria() {
            return new Criteria(1, 25);
        },
    },
});

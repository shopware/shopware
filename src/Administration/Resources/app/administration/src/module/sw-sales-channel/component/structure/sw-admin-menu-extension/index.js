/**
 * @package buyers-experience
 */

import template from './sw-admin-menu-extension.html.twig';

const { Component } = Shopware;

Component.override('sw-admin-menu', {
    template,

    compatConfig: Shopware.compatConfig,

    inject: ['acl'],

    computed: {
        canViewSalesChannels() {
            return this.acl.can('sales_channel.viewer');
        },
    },
});

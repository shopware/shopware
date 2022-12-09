/**
 * @package sales-channel
 */

import template from './sw-admin-menu-extension.html.twig';

const { Component } = Shopware;

Component.override('sw-admin-menu', {
    template,

    inject: ['acl'],

    computed: {
        canViewSalesChannels() {
            return this.acl.can('sales_channel.viewer');
        },
    },
});

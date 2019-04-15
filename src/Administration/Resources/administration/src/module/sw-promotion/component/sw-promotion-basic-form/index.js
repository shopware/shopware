import { Component, Mixin, State } from 'src/core/shopware';
import template from './sw-promotion-basic-form.html.twig';
import './sw-promotion-basic-form.scss';

Component.register('sw-promotion-basic-form', {
    template,

    mixins: [
        Mixin.getByName('placeholder')
    ],

    props: {
        promotion: {
            type: Object,
            required: true,
            default: {}
        }
    },

    computed: {
        salesChannelStore() {
            return State.getStore('sales_channel');
        },
        salesChannelAssociationStore() {
            return this.promotion.getAssociation('salesChannels');
        }
    }

});

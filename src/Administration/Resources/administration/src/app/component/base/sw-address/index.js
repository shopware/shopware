import { Component } from 'src/core/shopware';
import template from './sw-address.html.twig';
import './sw-address.less';

Component.register('sw-address', {
    template,

    props: {
        address: {
            type: Object,
            required: true,
            default: {}
        }
    },

    computed: {
        fullName() {
            const salutation = this.address.salutation ? `${this.address.salutation} ` : '';
            const title = this.address.title ? `${this.address.title} ` : '';
            const firstName = this.address.firstName ? `${this.address.firstName} ` : '';
            const lastName = this.address.lastName ? this.address.lastName : '';

            return salutation + title + firstName + lastName;
        }
    }
});

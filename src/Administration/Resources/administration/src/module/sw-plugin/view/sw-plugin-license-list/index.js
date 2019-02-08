import { Component, Mixin } from 'src/core/shopware';
import template from './sw-plugin-license-list.twig';
import './sw-plugin-licenses-list.scss';

Component.register('sw-plugin-license-list', {
    template,

    inject: ['storeService'],

    mixins: [
        Mixin.getByName('listing')
    ],

    data() {
        return {
            limit: 25,
            licenses: [],
            isLoading: false,
            showLoginModal: true
        };
    },

    computed: {
        showPagination() {
            return (this.total >= 25);
        }
    },

    watch: {
        '$root.$i18n.locale'() {
            this.getList();
        }
    },

    methods: {

        onDownload() {
        },

        getList() {
            this.storeService.getLicenseList().then((data) => {
                this.licenses = data.items;
            });
        },

        loginSuccess() {
            this.showLoginModal = false;
        }
    }
});

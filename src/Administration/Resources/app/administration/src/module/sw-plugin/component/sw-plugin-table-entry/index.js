import template from './sw-plugin-table-entry.html.twig';
import './sw-plugin-table-entry.scss';

const { Component } = Shopware;

Component.register('sw-plugin-table-entry', {
    template,

    props: {
        icon: {
            type: String,
            required: false
        },

        iconPath: {
            type: String,
            required: false
        },

        title: {
            type: String,
            required: true
        },

        subtitle: {
            type: String,
            required: true
        },

        licenseInformation: {
            type: Array,
            required: false,
            default() {
                return [];
            }
        }
    },

    computed: {
        iconSrc() {
            if (this.icon) {
                return `data:image/png;base64,${this.icon}`;
            }

            if (this.iconPath) {
                return this.iconPath;
            }

            return 'data:image/gif;base64,R0lGODlhKAAoAIAAAAAAAAAAACH5BAEAAAAALAAAAAAoACgAAAInhI+py+0Po5y02ouz3rz7D4biSJbmiabqyrbuC8fyTNf2jef6ziMFADs=';
        }
    },

    methods: {
        labelVariant(licenseInfo) {
            if (licenseInfo.level === 'violation') {
                return 'danger';
            }

            if (licenseInfo.level === 'warning') {
                return 'warning';
            }

            return 'info';
        }
    }
});

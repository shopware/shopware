import template from './sw-plugin-table-entry.html.twig';
import './sw-plugin-table-entry.scss';

/**
 * @feature-deprecated (flag:FEATURE_NEXT_12608) tag:v6.4.0
 * Deprecation notice: The whole plugin manager will be removed with 6.4.0 and replaced
 * by the extension module.
 * When removing the feature flag for FEATURE_NEXT_12608, also merge the merge request
 * for NEXT-13821 which removes the plugin manager.
 */

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

import { Component } from 'src/core/shopware';
import template from './sw-configuration-detail-base.html.twig';

Component.register('sw-configuration-detail-base', {
    template,

    props: {
        group: {
            type: Object,
            required: true,
            default() {
                return {};
            }
        }
    },

    data() {
        return {
            sortingTypes: [
                { value: 'numeric', label: this.$tc('sw-configuration.detail.numericSortingType') },
                { value: 'alphanumeric', label: this.$tc('sw-configuration.detail.alphanumericSortingType') },
                { value: 'position', label: this.$tc('sw-configuration.detail.positionSortingType') }
            ],
            displayTypes: [
                { value: 'media', label: this.$tc('sw-configuration.detail.mediaDisplayType') },
                { value: 'text', label: this.$tc('sw-configuration.detail.textDisplayType') },
                { value: 'color', label: this.$tc('sw-configuration.detail.colorDisplayType') }
            ]
        };
    }
});

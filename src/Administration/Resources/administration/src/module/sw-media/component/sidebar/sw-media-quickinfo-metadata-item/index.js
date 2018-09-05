import { Component } from 'src/core/shopware';
import template from './sw-media-quickinfo-metadata-item.html.twig';
import './sw-media-quickinfo-metadata-item.less';

Component.register('sw-media-quickinfo-metadata-item', {
    template,

    props: {
        labelName: {
            required: true,
            type: String
        },
        value: {
            required: false,
            validator: (value) => {
                return (typeof value === 'string') || (typeof value === 'number');
            }
        },
        suffix: {
            required: false,
            default: '',
            type: String
        }
    },

    computed: {
        getLabel() {
            return this.$tc(`sw-media.sidebar.metadata.${this.labelName}`);
        },

        getValue() {
            if (!this.value) {
                return 'unknown';
            }

            return this.value + this.suffix;
        }
    }
});

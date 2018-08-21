import { Component } from 'src/core/shopware';
import template from './sw-image.html.twig';
import './sw-image.less';

Component.register('sw-image', {
    template,

    props: {
        altText: {
            type: String,
            required: false,
            default: ''
        },

        isCover: {
            type: Boolean,
            required: false,
            default: false
        },

        downloadable: {
            type: Boolean,
            required: false,
            default: true
        },

        url: {
            type: String,
            required: false,
            default: ''
        }
    },

    computed: {
        swImageClasses() {
            return {
                'is--cover': this.isCover
            };
        }
    }
});

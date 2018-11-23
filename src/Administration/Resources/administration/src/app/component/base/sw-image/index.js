import { Component } from 'src/core/shopware';
import template from './sw-image.html.twig';
import './sw-image.less';

/**
 * @public
 * @description Component which renders an image.
 * @status ready
 * @example-type static
 * @component-example
 * <sw-image url="https://via.placeholder.com/350x150" altText="Example image"></sw-image>
 */
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

        isPlaceholder: {
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
                'is--placeholder': this.isPlaceholder,
                'is--cover': this.isCover
            };
        }
    }
});

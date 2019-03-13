import { Component } from 'src/core/shopware';
import template from './sw-media-tag.html.twig';
import './sw-media-tag.scss';

Component.register('sw-media-tag', {
    template,

    props: {
        media: {
            type: Object,
            required: true
        }
    },

    methods: {
        onChange() {
            this.media.save();
        }
    }
});

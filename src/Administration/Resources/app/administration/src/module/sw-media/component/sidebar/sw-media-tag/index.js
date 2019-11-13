import template from './sw-media-tag.html.twig';
import './sw-media-tag.scss';

const { Component } = Shopware;

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

import { Component } from 'src/core/shopware';

Component.extend('sw-media-replace', 'sw-media-upload', {
    props: {
        itemToReplace: {
            type: Object,
            required: true
        }
    },

    data() {
        return {
            multiSelect: false
        };
    },

    methods: {
        getMediaEntityForUpload() {
            return this.itemToReplace;
        }
    }
});

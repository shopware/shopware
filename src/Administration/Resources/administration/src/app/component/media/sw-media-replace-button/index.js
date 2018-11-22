import { Component } from 'src/core/shopware';

Component.extend('sw-media-replace-button', 'sw-media-upload-button', {
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

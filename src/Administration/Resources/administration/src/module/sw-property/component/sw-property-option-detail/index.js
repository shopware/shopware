import { Component, State } from 'src/core/shopware';
import template from './sw-property-option-detail.html.twig';

Component.register('sw-property-option-detail', {
    template,

    props: {
        currentOption: {
            type: Object,
            default() {
                return {};
            }
        }
    },

    computed: {
        mediaStore() {
            return State.getStore('media');
        },

        uploadStore() {
            return State.getStore('upload');
        }
    },

    methods: {
        onCancel() {
            if (this.currentOption !== null) {
                this.currentOption.discardChanges();
            }
            this.$emit('cancel-option-edit', this.currentOption);
        },

        onSave() {
            this.$emit('save-option-edit', this.currentOption);
        },

        onUploadsAdded({ data }) {
            if (data.length === 0) {
                return;
            }

            this.mediaStore.sync().then(() => {
                data.forEach((upload) => {
                    this.currentOption.mediaId = upload.targetId;
                });
                this.uploadStore.runUploads(this.currentOption.id);
            });
        },

        successfulUpload({ targetId }) {
            this.mediaStore.getByIdAsync(targetId).then(() => {
                this.$forceUpdate();
            });
        },

        removeMedia() {
            this.currentOption.mediaId = null;
        },

        setMedia(selection) {
            this.currentOption.mediaId = selection[0].id;
        }
    }
});

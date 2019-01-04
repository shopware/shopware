import { Component, State } from 'src/core/shopware';
import template from './sw-configuration-option-detail.html.twig';

Component.register('sw-configuration-option-detail', {
    template,

    props: {
        currentOption: {
            type: Object,
            default() {
                return {};
            }
        }
    },

    data() {
        return {
            media: {}
        };
    },

    computed: {
        mediaStore() {
            return State.getStore('media');
        },
        uploadStore() {
            return State.getStore('upload');
        }
    },

    mounted() {
        this.mountedComponent();
    },

    methods: {
        mountedComponent() {
            if (this.currentOption.mediaId) {
                this.media = this.mediaStore.getById(this.currentOption.mediaId);
            }
        },

        onCancel() {
            if (this.currentOption !== null) {
                this.currentOption.discardChanges();
            }
            this.$emit('cancel-option-edit', this.currentOption);
        },

        onSave() {
            this.$emit('save-option-edit', this.currentOption);
        },

        onUploadAdded({ uploadTag }) {
            this.currentOption.isLoading = true;

            this.mediaStore.sync().then(() => {
                return this.uploadStore.runUploads(uploadTag);
            }).finally(() => {
                this.currentOption.isLoading = false;
            });
        },

        setMediaItem(mediaEntity) {
            this.currentOption.mediaId = mediaEntity.id;
            this.media = mediaEntity;
        },

        onUnlinkMedia() {
            this.currentOption.mediaId = null;
        }
    }
});

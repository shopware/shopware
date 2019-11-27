import template from './sw-property-option-detail.html.twig';

const { Component, StateDeprecated } = Shopware;

Component.register('sw-property-option-detail', {
    template,

    inject: [
        'repositoryFactory'
    ],

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
            return StateDeprecated.getStore('media');
        },

        mediaRepository() {
            return this.repositoryFactory.create('media');
        },

        uploadStore() {
            return StateDeprecated.getStore('upload');
        }
    },

    methods: {
        onCancel() {
            this.$emit('cancel-option-edit', this.currentOption);
        },

        onSave() {
            this.$emit('save-option-edit', this.currentOption);
        },

        successfulUpload({ targetId }) {
            this.currentOption.mediaId = targetId;
            return this.mediaRepository.get(targetId, Shopware.Context.api)
                .then((media) => { return media; });
        },

        removeMedia() {
            this.currentOption.mediaId = null;
        },

        setMedia(selection) {
            this.currentOption.mediaId = selection[0].id;
        }
    }
});

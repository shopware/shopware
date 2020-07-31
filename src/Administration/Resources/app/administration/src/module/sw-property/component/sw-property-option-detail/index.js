import template from './sw-property-option-detail.html.twig';

Shopware.Component.register('sw-property-option-detail', {
    template,

    inject: ['repositoryFactory', 'acl'],

    props: {
        currentOption: {
            type: Object,
            default() {
                return {};
            }
        }
    },

    computed: {
        mediaRepository() {
            return this.repositoryFactory.create('media');
        }
    },

    methods: {
        onCancel() {
            this.$emit('cancel-option-edit', this.currentOption);
        },

        onSave() {
            this.$emit('save-option-edit', this.currentOption);
        },

        async successfulUpload({ targetId }) {
            this.currentOption.mediaId = targetId;
            await this.mediaRepository.get(targetId, Shopware.Context.api);
        },

        removeMedia() {
            this.currentOption.mediaId = null;
        },

        setMedia(selection) {
            this.currentOption.mediaId = selection[0].id;
        }
    }
});

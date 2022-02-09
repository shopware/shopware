import template from './sw-property-option-detail.html.twig';

Shopware.Component.register('sw-property-option-detail', {
    template,

    inject: ['repositoryFactory', 'acl'],

    props: {
        currentOption: {
            type: Object,
            default() {
                return {};
            },
        },
        allowEdit: {
            type: Boolean,
            required: false,
            // TODO: Boolean props should only be opt in and therefore default to false
            // eslint-disable-next-line vue/no-boolean-default
            default: true,
        },
    },

    computed: {
        mediaRepository() {
            return this.repositoryFactory.create('media');
        },
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
            await this.mediaRepository.get(targetId);
        },

        removeMedia() {
            this.currentOption.mediaId = null;
        },

        setMedia(selection) {
            this.currentOption.mediaId = selection[0].id;
        },
    },
});

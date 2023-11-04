/*
 * @package inventory
 */

import template from './sw-property-option-detail.html.twig';

const { Component } = Shopware;
const { mapPropertyErrors } = Component.getComponentHelper();

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
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

        ...mapPropertyErrors('currentOption', ['name']),
    },

    methods: {
        onCancel() {
            // Remove all property group options
            Shopware.State.dispatch(
                'error/removeApiError',
                { expression: 'property_group_option' },
            );

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
};

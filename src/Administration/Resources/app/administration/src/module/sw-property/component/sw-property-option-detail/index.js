/*
 * @sw-package inventory
 */

import template from './sw-property-option-detail.html.twig';

const { Component, Mixin } = Shopware;
const { mapPropertyErrors } = Component.getComponentHelper();

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: [
        'repositoryFactory',
        'acl',
        'customFieldDataProviderService',
    ],

    mixins: [
        Mixin.getByName('placeholder'),
    ],

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
            // eslint-disable-next-line vue/no-boolean-default
            default: true,
        },
    },

    emits: [
        'cancel-option-edit',
        'save-option-edit',
    ],

    data() {
        return {
            customFieldSets: null,
        };
    },

    computed: {
        mediaRepository() {
            return this.repositoryFactory.create('media');
        },

        colorHexCode: {
            set(value) {
                this.currentOption.colorHexCode = value;
            },

            get() {
                return this.currentOption?.colorHexCode || '';
            },
        },

        modalTitle() {
            return this.currentOption?.translated?.name || this.$tc('sw-property.detail.textOptionHeadline');
        },

        ...mapPropertyErrors('currentOption', ['name']),

        showCustomFields() {
            return this.currentOption && this.customFieldSets && this.customFieldSets.length > 0;
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.loadCustomFieldSets();
        },

        loadCustomFieldSets() {
            this.customFieldDataProviderService.getCustomFieldSets('property_group_option').then((sets) => {
                this.customFieldSets = sets;
            });
        },
        onCancel() {
            // Remove all property group options
            Shopware.Store.get('error').removeApiError('property_group_option');

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

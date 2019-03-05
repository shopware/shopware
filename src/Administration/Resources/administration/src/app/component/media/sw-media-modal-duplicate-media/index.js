import { Component, State } from 'src/core/shopware';
import CriteriaFactory from 'src/core/factory/criteria.factory';
import template from './sw-media-modal-duplicate-media.html.twig';
import './sw-media-modal-duplicate-media.scss';

/**
 * @status ready
 * @description The <u>sw-media-modal-duplicate-media</u> component is used to resolve situations
 * when an image can not be uploaded because an image with the same name already exists.
 * @example-type code-only
 * @component-example
 * <sw-media-modal-duplicate-media >
 *     :item="media"
 * </sw-media-modal-duplicate-media>
 */
Component.register('sw-media-modal-duplicate-media', {
    template,

    inject: ['mediaService'],

    props: {
        item: {
            required: true,
            type: Object,
            validator(value) {
                return (value.getEntityName() === 'media');
            }
        },

        additionalErrorCount: {
            required: false,
            type: Number,
            default: 0
        },

        defaultOption: {
            required: false,
            type: String,
            default: 'Replace'
        },

        saveSelection: {
            required: false,
            type: Boolean,
            default: true
        }
    },

    data() {
        return {
            shouldSaveSelection: this.saveSelection,
            selectedOption: this.defaultOption,
            existingMedia: null,
            duplicateName: this.item.fileName,
            newName: '',
            options: [
                {
                    value: 'Replace',
                    name: this.$tc('global.sw-media-modal-duplicate-media.labelOptionReplace')
                },
                {
                    value: 'Rename',
                    name: this.$tc('global.sw-media-modal-duplicate-media.labelOptionRename')
                },
                {
                    value: 'Skip',
                    name: this.$tc('global.sw-media-modal-duplicate-media.labelOptionSkip')
                }
            ]
        };
    },

    computed: {
        mediaStore() {
            return State.getStore('media');
        },

        buttonLabel() {
            return this.$tc(`global.sw-media-modal-duplicate-media.button${this.selectedOption}`);
        }
    },

    watch: {
        selectedOption() {
            if (this.selectedOption === 'Rename') {
                this.item.fileName = this.newName;

                return;
            }

            this.item.fileName = this.duplicateName;
        }
    },

    created() {
        this.componentCreated();
    },

    methods: {
        componentCreated() {
            this.mediaStore.getList({
                page: 1,
                limit: 1,
                criteria: CriteriaFactory.multi('AND',
                    CriteriaFactory.equals('fileName', this.item.fileName),
                    CriteriaFactory.equals('fileExtension', this.item.fileExtension))
            }).then((response) => {
                this.existingMedia = response.items[0];
            });

            this.mediaService.provideName(this.item.fileName, this.item.fileExtension)
                .then((response) => {
                    this.newName = response.fileName;
                    if (this.selectedOption === 'Rename') {
                        this.item.fileName = this.newName;
                    }
                });
        },

        closeModal() {
            this.$emit('sw-media-modal-duplicate-media-close', { id: this.item.id });
        },

        abortResolving() {
            this.$emit('sw-media-modal-duplicate-media-abort');
        },

        solveDuplicate() {
            this.$emit('sw-media-modal-duplicate-media-resolve', {
                action: this.selectedOption,
                id: this.item.id,
                saveSelection: this.shouldSaveSelection,
                entityToReplace: this.existingMedia,
                newName: this.newName
            });
        }
    }
});

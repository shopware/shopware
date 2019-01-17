import { Component, Mixin, State } from 'src/core/shopware';
import CriteriaFactory from 'src/core/factory/criteria.factory';
import template from './sw-media-modal-duplicate-media.html.twig';
import './sw-media-modal-duplicate-media.less';

/**
 * @status ready
 * @description The <u>sw-media-modal-duplicate-media</u> component is used to validate the dissolve folder action.
 * @example-type code-only
 * @component-example
 * <sw-media-modal-duplicate-media >
 * </sw-media-modal-duplicate-media>
 */
Component.register('sw-media-modal-duplicate-media', {
    template,

    inject: ['mediaService'],

    mixins: [
        Mixin.getByName('notification')
    ],

    props: {
        item: {
            required: true,
            type: Object,
            validator(value) {
                return (value.entityName === 'media');
            }
        }
    },

    data() {
        return {
            saveSelection: true,
            selectedOption: 'Replace',
            existingMedia: null,
            options: [
                {
                    value: 'Replace',
                    label: this.$tc('global.sw-media-modal-duplicate-media.labelOptionReplace')
                },
                {
                    value: 'Rename',
                    label: this.$tc('global.sw-media-modal-duplicate-media.labelOptionRename')
                },
                {
                    value: 'Skip',
                    label: this.$tc('global.sw-media-modal-duplicate-media.labelOptionSkip')
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
        },

        closeModal(originalDomEvent) {
            this.$emit('sw-media-modal-duplicate-media-close', { originalDomEvent });
        }
    }
});

import CriteriaFactory from 'src/core/factory/criteria.factory';
import template from './sw-media-field.html.twig';
import './sw-media-field.scss';

const { Component, StateDeprecated } = Shopware;
const utils = Shopware.Utils;

/**
 * @status ready
 * @description The <u>sw-media-field</u> component is used to bind your
 * @example-type code-only
 * @component-example
 * <sw-media-field v-model="manufacturer.mediaId"></sw-media-field>
 */
Component.register('sw-media-field', {
    template,

    model: {
        prop: 'mediaId',
        event: 'media-id-change'
    },

    props: {
        mediaId: {
            type: String,
            required: false
        },

        label: {
            type: String,
            required: false
        }
    },

    data() {
        return {
            searchTerm: '',
            mediaEntity: null,
            showPicker: false,
            showUploadField: false,
            suggestedItems: [],
            isLoadingSuggestions: false,
            pickerClasses: {},
            uploadTag: utils.createId()
        };
    },

    created() {
        this.createdComponent();
    },

    watch: {
        mediaId(newValue) {
            this.fetchItem(newValue);
            this.$emit('media-id-change', newValue);
        },

        searchTerm() {
            this.fetchSuggestions();
        }
    },

    computed: {
        mediaStore() {
            return StateDeprecated.getStore('media');
        },

        mediaFieldClasses() {
            return {
                'is--active': this.showPicker
            };
        },

        toggleButtonLabel() {
            return this.showUploadField ?
                this.$tc('global.sw-media-field.labelToggleSearchExisting') :
                this.$tc('global.sw-media-field.labelToggleUploadNew');
        }
    },

    methods: {
        createdComponent() {
            this.fetchItem();
        },

        fetchItem(id = this.mediaId) {
            if (!id) {
                this.mediaEntity = null;
                return;
            }
            this.mediaStore.getByIdAsync(id).then((updatedEntity) => {
                this.mediaEntity = updatedEntity;
            });
        },

        fetchSuggestions() {
            this.isLoadingSuggestions = true;
            const searchCriteria = [
                CriteriaFactory.not('and', CriteriaFactory.equals('uploadedAt', null))
            ];

            if (this.searchTerm) {
                searchCriteria.push(
                    CriteriaFactory.multi('or',
                        CriteriaFactory.contains('fileName', this.searchTerm),
                        CriteriaFactory.contains('fileExtension', this.searchTerm))
                );
            }

            this.mediaStore.getList({
                limit: 5,
                offset: 0,
                criteria: CriteriaFactory.multi('and', ...searchCriteria)
            }).then((response) => {
                this.suggestedItems = response.items;
            }).finally(() => {
                this.isLoadingSuggestions = false;
            });
        },

        onTogglePicker() {
            this.showPicker = !this.showPicker;

            if (this.showPicker) {
                this.showUploadField = false;
                this.computePickerPositionAndStyle();
                this.fetchSuggestions();
            }
        },

        mediaItemChanged(newMediaId) {
            this.$emit('media-id-change', newMediaId);
            this.onTogglePicker();
        },

        removeLink() {
            this.$emit('media-id-change', null);
        },

        computePickerPositionAndStyle() {
            if (!this.$el) {
                this.pickerClasses = {};
                return;
            }

            const clientRect = this.$el.getBoundingClientRect();
            this.pickerClasses = {
                top: `${clientRect.height + 5}px`
            };
        },

        toggleUploadField() {
            this.showUploadField = !this.showUploadField;
        },

        exposeNewId({ targetId }) {
            this.$emit('media-id-change', targetId);
            this.showUploadField = false;
            this.showPicker = false;
        }
    }
});

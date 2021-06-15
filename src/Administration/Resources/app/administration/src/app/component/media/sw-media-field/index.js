import template from './sw-media-field.html.twig';
import './sw-media-field.scss';

const { Component, Context, Utils } = Shopware;
const { Criteria } = Shopware.Data;

/**
 * @status ready
 * @description The <u>sw-media-field</u> component is used to bind your
 * @example-type code-only
 * @component-example
 * <sw-media-field v-model="manufacturer.mediaId"></sw-media-field>
 */
Component.register('sw-media-field', {
    template,

    inject: ['repositoryFactory'],

    model: {
        prop: 'mediaId',
        event: 'media-id-change',
    },

    props: {
        disabled: {
            type: Boolean,
            default: false,
            required: false,
        },

        mediaId: {
            type: String,
            required: false,
            default: null,
        },

        label: {
            type: String,
            required: false,
            default: null,
        },
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
            uploadTag: Utils.createId(),
        };
    },

    computed: {
        mediaRepository() {
            return this.repositoryFactory.create('media');
        },
        mediaFieldClasses() {
            return {
                'is--active': this.showPicker,
            };
        },

        toggleButtonLabel() {
            return this.showUploadField ?
                this.$tc('global.sw-media-field.labelToggleSearchExisting') :
                this.$tc('global.sw-media-field.labelToggleUploadNew');
        },
    },

    watch: {
        mediaId(newValue) {
            this.fetchItem(newValue);
            this.$emit('media-id-change', newValue);
        },

        searchTerm() {
            this.fetchSuggestions();
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.fetchItem();
        },

        async fetchItem(id = this.mediaId) {
            if (!id) {
                this.mediaEntity = null;
                return;
            }
            this.mediaEntity = await this.mediaRepository.get(id, Context.api);
        },

        async fetchSuggestions() {
            this.isLoadingSuggestions = true;
            const criteria = new Criteria(1, 5);

            criteria.addFilter(Criteria.not(
                'AND',
                [Criteria.equals('uploadedAt', null)],
            ));

            if (this.searchTerm) {
                criteria.addFilter(Criteria.multi(
                    'OR',
                    [
                        Criteria.contains('fileName', this.searchTerm),
                        Criteria.contains('fileExtension', this.searchTerm),
                    ],
                ));
            }

            try {
                this.suggestedItems = await this.mediaRepository.search(criteria, Context.api);
            } catch (e) {
                throw new Error(e);
            } finally {
                this.isLoadingSuggestions = false;
            }
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
                top: `${clientRect.height + 5}px`,
            };
        },

        toggleUploadField() {
            this.showUploadField = !this.showUploadField;
        },

        exposeNewId({ targetId }) {
            this.$emit('media-id-change', targetId);
            this.showUploadField = false;
            this.showPicker = false;
        },
    },
});

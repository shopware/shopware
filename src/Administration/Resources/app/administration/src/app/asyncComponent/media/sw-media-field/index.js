import template from './sw-media-field.html.twig';
import './sw-media-field.scss';

const { Context, Utils } = Shopware;
const { Criteria } = Shopware.Data;

/**
 * @status ready
 * @description The <u>sw-media-field</u> component is used to bind your
 * @package content
 * @example-type code-only
 * @component-example
 * <sw-media-field v-model="manufacturer.mediaId"></sw-media-field>
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    compatConfig: Shopware.compatConfig,

    inject: ['repositoryFactory', 'feature'],

    props: {
        // need to be "value" instead of "modelValue" because of the compat build
        value: {
            type: String,
            required: false,
            default: null,
        },

        disabled: {
            type: Boolean,
            default: false,
            required: false,
        },

        label: {
            type: String,
            required: false,
            default: null,
        },

        defaultFolder: {
            type: String,
            required: false,
            validator(value) {
                return value.length > 0;
            },
            default: null,
        },

        fileAccept: {
            type: String,
            required: false,
            default: '*/*',
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
            page: 1,
            limit: 5,
            total: 0,
        };
    },

    computed: {
        mediaId: {
            get() {
                return this.value;
            },
            set(newValue) {
                this.$emit('update:value', newValue);
            },
        },

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

        suggestionCriteria() {
            const criteria = new Criteria(this.page, this.limit);

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

            if (this.defaultFolder) {
                criteria.addFilter(Criteria.equals('mediaFolder.defaultFolder.entity', this.defaultFolder));
            }

            return criteria;
        },
    },

    watch: {
        mediaId(newValue) {
            this.fetchItem(newValue);
            this.$emit('update:value', newValue);
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.fetchItem();
        },

        onSearchTermChange(searchTerm) {
            this.searchTerm = searchTerm;
            this.page = 1;
            this.fetchSuggestions();
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

            try {
                this.suggestedItems = await this.mediaRepository.search(this.suggestionCriteria, Context.api);
                this.total = this.suggestedItems.total;
            } catch (e) {
                throw new Error(e);
            } finally {
                this.isLoadingSuggestions = false;
            }
        },

        onTogglePicker() {
            this.page = 1;
            this.limit = 5;
            this.total = 0;
            this.showPicker = !this.showPicker;

            if (this.showPicker) {
                this.showUploadField = false;
                this.computePickerPositionAndStyle();
                this.fetchSuggestions();
            }
        },

        mediaItemChanged(newMediaId) {
            this.$emit('update:value', newMediaId);
            this.onTogglePicker();
        },

        removeLink() {
            this.$emit('update:value', null);
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
            this.$emit('update:value', targetId);
            this.showUploadField = false;
            this.showPicker = false;
        },

        showLabel() {
            return !!this.label || !!this.$slots.label || !!this.$scopedSlots?.label?.();
        },

        onPageChange({ page, limit }) {
            this.page = page;
            this.limit = limit;

            this.fetchSuggestions();
        },
    },
};

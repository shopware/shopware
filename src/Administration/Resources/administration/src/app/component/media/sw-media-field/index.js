import { State, Mixin } from 'src/core/shopware';
import CriteriaFactory from 'src/core/factory/criteria.factory';
import utils from 'src/core/service/util.service';
import template from './sw-media-field.html.twig';
import './sw-media-field.scss';

/**
 * @status ready
 * @description The <u>sw-media-field</u> component is used to bind your
 * @example-type code-only
 * @component-example
 * <sw-media-field v-model="manufacturer.mediaId"></sw-media-field>
 */
export default {
    name: 'sw-media-field',
    template,

    model: {
        prop: 'mediaId',
        event: 'mediaIdChanged'
    },

    mixins: [
        Mixin.getByName('sw-inline-snippet')
    ],

    props: {
        mediaId: {
            type: String,
            required: false
        },

        label: {
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
        this.componentCreated();
    },

    watch: {
        mediaId(newValue) {
            this.fetchItem(newValue);
            this.$emit('mediaIdChanged', newValue);
        },

        searchTerm() {
            this.fetchSuggestions();
        }
    },

    computed: {
        mediaStore() {
            return State.getStore('media');
        },

        uploadStore() {
            return State.getStore('upload');
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
        componentCreated() {
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
            this.$emit('mediaIdChanged', newMediaId);
            this.onTogglePicker();
        },

        removeLink() {
            this.$emit('mediaIdChanged', null);
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
            this.$emit('mediaIdChanged', targetId);
            this.showUploadField = false;
            this.showPicker = false;
        }
    }
};

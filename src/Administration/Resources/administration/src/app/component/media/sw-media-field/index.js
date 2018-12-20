import { Component, State } from 'src/core/shopware';
import CriteriaFactory from 'src/core/factory/criteria.factory';
import template from './sw-media-field.html.twig';
import './sw-media-field.less';

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
        event: 'mediaIdChanged'
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
            value: '',
            mediaEntity: null,
            showPicker: false,
            searchTerm: '',
            suggestedItems: [],
            isLoadingSuggestions: false,
            pickerClasses: {}
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

        mediaFieldClasses() {
            return {
                'is--active': this.showPicker
            };
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
            this.mediaEntity = this.mediaStore.getById(id);
        },

        fetchSuggestions() {
            this.isLoadingSuggestions = true;
            const searchCriteria = [
                CriteriaFactory.not('and', CriteriaFactory.equals('uploadedAt', null))
            ];

            if (this.searchTerm) {
                searchCriteria.push(CriteriaFactory.equals('fileName', this.searchTerm));
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
                this.computePickerPositionAndStyle();
                this.fetchSuggestions();
            }
        },

        mediaItemChanged(newMediaId) {
            this.$emit('mediaIdChanged', newMediaId);
        },

        computePickerPositionAndStyle() {
            if (!this.$el) {
                this.pickerClasses = {};
                return;
            }

            const clientRect = this.$el.getBoundingClientRect();
            this.pickerClasses = {
                width: '100%',
                top: `${clientRect.height + 10}px`
            };
        }
    }
});

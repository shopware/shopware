import { Component, Mixin } from 'src/core/shopware';
import types from 'src/core/service/utils/types.utils';
import template from './sw-attribute-list.html.twig';
import './sw-attribute-list.scss';


Component.register('sw-attribute-list', {
    template,

    mixins: [
        Mixin.getByName('listing'),
        Mixin.getByName('sw-inline-snippet')
    ],

    props: {
        set: {
            type: Object,
            required: true
        }
    },

    data() {
        return {
            limit: 10,
            attributes: [],
            isLoading: false,
            currentAttribute: null,
            searchTerm: '',
            deleteButtonDisabled: true,
            disableRouteParams: true
        };
    },

    computed: {
        attributeStore() {
            return this.set.getAssociation('attributes');
        }
    },

    methods: {
        onSearch(value) {
            if (!this.hasExistingAttributes()) {
                this.term = '';
                return;
            }

            this.term = value;

            this.page = 1;
            this.getList();
        },

        hasExistingAttributes() {
            return Object.values(this.attributeStore.store).some((item) => {
                return !item.isLocal;
            });
        },

        getList() {
            this.isLoading = true;
            const params = this.getListingParams();
            params.sortBy = 'attribute.config.attributePosition';

            this.attributes = [];
            return this.attributeStore.getList(params).then((response) => {
                this.total = response.total;
                this.attributes = response.items;
                this.isLoading = false;

                this.buildGridArray();

                return this.attributes;
            });
        },

        selectionChanged() {
            const selection = this.$refs.grid.getSelection();
            this.deleteButtonDisabled = Object.keys(selection).length <= 0;
        },

        newItems() {
            const items = [];
            this.attributeStore.forEach((item) => {
                if (item.isLocal) {
                    items.push(item);
                }
            });
            return items;
        },

        onAttributeDelete(attribute) {
            attribute.delete();

            if (attribute.isLocal) {
                this.attributeStore.removeById(attribute.id);

                this.attributes.forEach((item, index) => {
                    if (item.id === attribute.id) {
                        this.attributes.splice(index, 1);
                    }
                });

                this.buildGridArray();
            }
        },

        onDeleteAttributes() {
            const selection = this.$refs.grid.getSelection();

            Object.values(selection).forEach((attribute) => {
                this.onAttributeDelete(attribute);
                this.$refs.grid.selectItem(false, attribute);
            });
        },

        onAddAttribute() {
            const attribute = this.attributeStore.create();
            this.attributeStore.removeById(attribute.id);
            this.onAttributeEdit(attribute);
        },

        onCancelAttribute() {
            this.currentAttribute = null;
        },

        onSaveAttribute() {
            this.removeEmptyProperties(this.currentAttribute.config);
            if (!this.attributeStore.hasId(this.currentAttribute.id)) {
                this.attributeStore.add(this.currentAttribute);
                this.buildGridArray();
            }

            this.currentAttribute = null;
        },

        onAttributeResetDelete(attribute) {
            attribute.isDeleted = false;
        },

        onInlineEditCancel(attribute) {
            attribute.discardChanges();
        },

        onAttributeEdit(attribute) {
            this.currentAttribute = attribute;
        },

        buildGridArray() {
            this.attributes = this.attributes.filter((value) => {
                return value.isLocal === false;
            });
            this.attributes.unshift(...this.newItems());
        },

        removeEmptyProperties(config) {
            Object.keys(config).forEach((property) => {
                if (['number', 'boolean'].includes(typeof config[property])) {
                    return;
                }

                if (types.isObject(config[property]) || types.isArray(config[property])) {
                    this.removeEmptyProperties(config[property]);
                }

                if (types.isEmpty(config[property]) || config[property] === undefined) {
                    this.$delete(config, property);
                }
            });
        }
    }
});

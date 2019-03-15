import { Component, State, Mixin } from 'src/core/shopware';
import CriteriaFactory from 'src/core/factory/criteria.factory';
import types from 'src/core/service/utils/types.utils';
import template from './sw-attribute-list.html.twig';
import './sw-attribute-list.scss';


Component.register('sw-attribute-list', {
    template,

    mixins: [
        Mixin.getByName('listing'),
        Mixin.getByName('sw-inline-snippet')
    ],

    provide() {
        return {
            SwAttributeListIsAttributeNameUnique: this.isAttributeNameUnique
        };
    },

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
            deleteButtonDisabled: true,
            disableRouteParams: true
        };
    },

    computed: {
        attributeAssociationStore() {
            return this.set.getAssociation('attributes');
        },
        attributeStore() {
            return State.getStore('attribute');
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
            return Object.values(this.attributeAssociationStore.store).some((item) => {
                return !item.isLocal;
            });
        },

        getList() {
            this.isLoading = true;
            const params = this.getListingParams();
            params.sortBy = 'attribute.config.attributePosition';

            if (params.term) {
                params.criteria = CriteriaFactory.multi(
                    'OR',
                    ...this.getLocaleCriterias(params.term),
                    CriteriaFactory.contains('name', params.term)
                );

                params.term = '';
            }

            this.attributes = [];
            return this.attributeAssociationStore.getList(params).then((response) => {
                this.total = response.total;
                this.attributes = response.items;
                this.isLoading = false;

                this.buildGridArray();

                return this.attributes;
            });
        },

        getLocaleCriterias(term) {
            const criterias = [];
            const locales = Object.keys(this.$root.$i18n.messages);

            locales.forEach(locale => {
                criterias.push(CriteriaFactory.contains(`config.label.\"${locale}\"`, term));
            });

            return criterias;
        },

        selectionChanged() {
            const selection = this.$refs.grid.getSelection();
            this.deleteButtonDisabled = Object.keys(selection).length <= 0;
        },

        newItems() {
            const items = [];
            this.attributeAssociationStore.forEach((item) => {
                if (item.isLocal) {
                    items.push(item);
                }
            });
            return items;
        },

        onAttributeDelete(attribute) {
            attribute.delete();

            if (attribute.isLocal) {
                this.attributeAssociationStore.removeById(attribute.id);

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
            const attribute = this.attributeAssociationStore.create();
            this.attributeAssociationStore.removeById(attribute.id);
            this.onAttributeEdit(attribute);
        },

        onCancelAttribute() {
            this.currentAttribute = null;
        },

        onSaveAttribute() {
            this.removeEmptyProperties(this.currentAttribute.config);
            if (!this.attributeAssociationStore.hasId(this.currentAttribute.id)) {
                this.attributeAssociationStore.add(this.currentAttribute);
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

                if ((types.isEmpty(config[property]) || config[property] === undefined) && config[property !== null]) {
                    this.$delete(config, property);
                }
            });
        },
        isAttributeNameUnique(attribute) {
            // Search in local attribute list for name
            const isUnique = !this.attributes.some((attr) => {
                if (attribute.id === attr.id) {
                    return false;
                }
                return attr.name === attribute.name;
            });

            if (!isUnique) {
                return Promise.resolve(false);
            }

            // Search the server for the attribute name
            const criteria = CriteriaFactory.equals('name', attribute.name);
            return this.attributeStore.getList({ criteria }).then((res) => {
                return res.total === 0;
            });
        }
    }
});

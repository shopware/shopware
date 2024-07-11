import template from './sw-condition-modal.html.twig';
import './sw-condition-modal.scss';

const { Component } = Shopware;
const { EntityCollection } = Shopware.Data;

/**
 * @private
 * @package services-settings
 */
Component.register('sw-condition-modal', {
    template,

    compatConfig: Shopware.compatConfig,

    inject: ['repositoryFactory'],

    emits: [
        'modal-close',
    ],

    props: {
        conditionDataProviderService: {
            type: Object,
            required: true,
        },

        condition: {
            type: Object,
            required: false,
            default: null,
        },

        scopes: {
            type: Array,
            required: false,
            default() {
                return ['lineItem'];
            },
        },

        allowedTypes: {
            type: Array,
            required: false,
            default: null,
        },

        childAssociationField: {
            type: String,
            required: false,
            default: 'children',
        },
    },

    data() {
        return {
            childConditions: null,
            deletedIds: [],
        };
    },

    computed: {
        conditionRepository() {
            return this.repositoryFactory.create(
                this.condition[this.childAssociationField].entity,
                this.condition[this.childAssociationField].source,
            );
        },

        initialConditions() {
            return this.condition[this.childAssociationField];
        },
    },

    methods: {
        onConditionsChanged({ conditions, deletedIds }) {
            this.childConditions = conditions;
            this.deletedIds = [...this.deletedIds, ...deletedIds];
        },

        deleteAndClose() {
            const childrenToDelete = this.condition[this.childAssociationField].filter((child) => !child.isNew()).getIds();

            this.deleteChildren(childrenToDelete, this.condition[this.childAssociationField].context).then(() => {
                // eslint-disable-next-line vue/no-mutating-props
                this.condition[this.childAssociationField] = new EntityCollection(
                    this.condition[this.childAssociationField].source,
                    this.condition[this.childAssociationField].entity,
                    this.condition[this.childAssociationField].context,
                );
                this.closeModal();
            });
        },

        saveAndCloseModal() {
            this.deleteChildren(this.deletedIds, this.condition[this.childAssociationField].context).then(() => {
                // eslint-disable-next-line vue/no-mutating-props
                this.condition[this.childAssociationField] = this.childConditions;
                this.closeModal();
            });
        },

        deleteChildren(ids, context) {
            if (ids.length <= 0) {
                return Promise.resolve();
            }

            return Promise.all(ids.map((id) => {
                return this.conditionRepository.delete(id, context);
            }));
        },

        closeModal() {
            this.$emit('modal-close');
        },
    },
});

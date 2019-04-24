import { Component, State, Mixin } from 'src/core/shopware';
import CriteriaFactory from 'src/core/factory/criteria.factory';
import template from './sw-order-detail.html.twig';
import './sw-order-detail.scss';

Component.register('sw-order-detail', {
    template,

    mixins: [
        Mixin.getByName('notification')
    ],

    data() {
        return {
            order: null,
            orderId: null,
            isEditing: false,
            attributeSets: []
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(this.identifier)
        };
    },

    computed: {
        identifier() {
            return this.order !== null ? this.order.orderNumber : '';
        },

        orderStore() {
            return State.getStore('order');
        },

        attributeSetStore() {
            return State.getStore('attribute_set');
        },

        showTabs() {
            return this.$route.meta.$module.routes.detail.children.length > 1;
        }
    },

    watch: {
        '$route.params.id'() {
            this.createdComponent();
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.orderId = this.$route.params.id;
            this.loadEntityData();
        },

        loadEntityData() {
            this.order = this.orderStore.getById(this.orderId);

            this.attributeSetStore.getList({
                page: 1,
                limit: 100,
                criteria: CriteriaFactory.equals('relations.entityName', 'order'),
                associations: {
                    attributes: {
                        limit: 100,
                        sort: 'attribute.config.attributePosition'
                    }
                }
            }, true).then((response) => {
                this.attributeSets = response.items;
            });
        },

        onChangeLanguage() {
            this.$refs.baseComponent.changeLanguage();
        },

        onSave() {
            this.isEditing = false;
            this.$refs.baseComponent.mergeOrder();
        },

        onStartEditing() {
            this.isEditing = true;
            this.$refs.baseComponent.startEditing();
        },

        onCancelEditing() {
            this.isEditing = false;
            this.$refs.baseComponent.cancelEditing();
        },

        onError(error) {
            this.createErrorNotification(error);
            this.onCancelEditing();
        },

        createErrorNotification(errorMessage) {
            this.createNotificationError({
                title: this.$tc('sw-order.detail.titleRecalculationError'),
                message: this.$tc('sw-order.detail.messageRecalculationError') + errorMessage
            });
        }
    }
});

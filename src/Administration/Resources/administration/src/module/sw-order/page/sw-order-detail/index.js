import { Component, State, Mixin } from 'src/core/shopware';
import template from './sw-order-detail.html.twig';
import './sw-order-detail.scss';

Component.register('sw-order-detail', {
    template,
    mixins: [
        Mixin.getByName('notification')
    ],
    data() {
        return {
            order: {},
            orderId: null,
            isEditing: false
        };
    },
    computed: {
        orderStore() {
            return State.getStore('order');
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
        },
        onChangeLanguage() {
            this.loadEntityData();
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

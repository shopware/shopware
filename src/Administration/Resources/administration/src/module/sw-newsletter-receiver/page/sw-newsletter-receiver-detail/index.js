import { Component, Mixin } from 'src/core/shopware';
import Criteria from 'src/core/data-new/criteria.data';
import template from './sw-newsletter-receiver-detail.html.twig';
import './sw-newsletter-receiver-detail.scss';

Component.register('sw-newsletter-receiver-detail', {
    template,

    inject: [
        'repositoryFactory',
        'context'
    ],

    mixins: [
        Mixin.getByName('notification')
    ],

    data() {
        return {
            newsletterReceiver: null,
            salutations: [],
            languages: [],
            salesChannels: [],
            isLoading: false
        };
    },

    computed: {
        newsletterReceiverStore() {
            return this.repositoryFactory.create('newsletter_receiver');
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.isLoading = true;
            const receiverCriteria = new Criteria(1, 1);

            receiverCriteria.addFilter(Criteria.equals('id', this.$route.params.id));
            this.newsletterReceiverStore.search(receiverCriteria, this.context).then((newsletterReceiver) => {
                this.newsletterReceiver = newsletterReceiver.first();
                this.isLoading = false;
            });
        },

        onClickSave() {
            this.newsletterReceiverStore.save(this.newsletterReceiver, this.context).then(() => {
                this.createNotificationSuccess({
                    title: this.$tc('sw-newsletter-receiver.detail.titleSaveSuccess'),
                    message: this.$tc(
                        'sw-newsletter-receiver.detail.messageSaveSuccess',
                        0,
                        { key: this.newsletterReceiver.email }
                    )
                });
            });
        }
    }
});

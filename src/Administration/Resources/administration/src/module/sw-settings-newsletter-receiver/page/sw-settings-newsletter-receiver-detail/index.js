import { Component, Mixin } from 'src/core/shopware';
import Criteria from 'src/core/data-new/criteria.data';
import template from './sw-settings-newsletter-receiver-detail.html.twig';
import './sw-settings-newsletter-receiver-detail.scss';

Component.register('sw-settings-newsletter-receiver-detail', {
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
        this.createComponent();
    },

    methods: {
        createComponent() {
            this.isLoading = true;
            const receiverCriteria = new Criteria(1, 1);

            receiverCriteria.addFilter(Criteria.equals('id', this.$route.params.id));
            this.newsletterReceiverStore.search(receiverCriteria, this.context).then((newsletterReceiver) => {
                this.newsletterReceiver = newsletterReceiver.first();
                this.isLoading = false;
            });
        },

        onClickSave() {
            this.newsletterReceiver.save().then((response) => {
                if (response.errors.length > 0) {
                    this.createNotificationError({
                        title: this.$tc('sw-settings-newsletter-receiver.detail.titleSaveError'),
                        message: this.$tc(
                            'sw-settings-newsletter-receiver.detail.messageSaveError',
                            0,
                            { key: this.newsletterReceiver.email }
                        )
                    });
                    return;
                }

                this.createNotificationSuccess({
                    title: this.$tc('sw-settings-newsletter-receiver.detail.titleSaveSuccess'),
                    message: this.$tc(
                        'sw-settings-newsletter-receiver.detail.messageSaveSuccess',
                        0,
                        { key: this.newsletterReceiver.email }
                    )
                });
            });
        }
    }
});

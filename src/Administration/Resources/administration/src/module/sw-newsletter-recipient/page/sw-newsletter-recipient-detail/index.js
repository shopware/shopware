import Criteria from 'src/core/data-new/criteria.data';
import template from './sw-newsletter-recipient-detail.html.twig';
import './sw-newsletter-recipient-detail.scss';

const { Component, Mixin } = Shopware;

Component.register('sw-newsletter-recipient-detail', {
    template,

    inject: [
        'repositoryFactory',
        'context'
    ],

    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('salutation')
    ],

    data() {
        return {
            newsletterRecipient: null,
            salutations: [],
            languages: [],
            salesChannels: [],
            isLoading: false
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(this.identifier)
        };
    },

    computed: {
        identifier() {
            return this.newsletterRecipient !== null ? this.salutation(this.newsletterRecipient) : '';
        },

        newsletterRecipientStore() {
            return this.repositoryFactory.create('newsletter_recipient');
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.isLoading = true;
            const recipientCriteria = new Criteria(1, 1);

            recipientCriteria.addFilter(Criteria.equals('id', this.$route.params.id));
            this.newsletterRecipientStore.search(recipientCriteria, this.context).then((newsletterRecipient) => {
                this.newsletterRecipient = newsletterRecipient.first();
                this.isLoading = false;
            });
        },

        onClickSave() {
            this.newsletterRecipientStore.save(this.newsletterRecipient, this.context).then(() => {
                this.createNotificationSuccess({
                    title: this.$tc('sw-newsletter-recipient.detail.titleSaveSuccess'),
                    message: this.$tc(
                        'sw-newsletter-recipient.detail.messageSaveSuccess',
                        0,
                        { key: this.newsletterRecipient.email }
                    )
                });
            });
        }
    }
});

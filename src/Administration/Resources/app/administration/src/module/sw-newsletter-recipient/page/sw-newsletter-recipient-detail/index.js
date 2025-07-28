import template from './sw-newsletter-recipient-detail.html.twig';
import './sw-newsletter-recipient-detail.scss';

const { Criteria } = Shopware.Data;

/**
 * @sw-package after-sales
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: [
        'repositoryFactory',
        'acl',
        'customFieldDataProviderService',
    ],

    mixins: [
        'notification',
        'salutation',
    ],

    data() {
        return {
            newsletterRecipient: null,
            salutations: [],
            languages: [],
            salesChannels: [],
            isLoading: false,
            isSaveSuccessful: false,
            customFieldSets: null,
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(this.identifier),
        };
    },

    computed: {
        identifier() {
            return this.newsletterRecipient !== null ? this.salutation(this.newsletterRecipient) : '';
        },

        newsletterRecipientStore() {
            return this.repositoryFactory.create('newsletter_recipient');
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.isLoading = true;

            Shopware.ExtensionAPI.publishData({
                id: 'sw-newsletter-recipient-detail__newsletterRecipient',
                path: 'newsletterRecipient',
                scope: this,
            });

            const recipientCriteria = new Criteria(1, 1)
                .addFilter(Criteria.equals('id', this.$route.params.id.toLowerCase()))
                .addAssociation('tags');

            this.newsletterRecipientStore
                .search(recipientCriteria)
                .then((newsletterRecipient) => {
                    this.newsletterRecipient = newsletterRecipient.first();
                    this.loadCustomFieldSets();
                })
                .finally(() => {
                    this.isLoading = false;
                });
        },

        onSave() {
            this.isLoading = true;
            this.isSaveSuccessful = false;

            this.newsletterRecipientStore
                .save(this.newsletterRecipient)
                .then(() => {
                    this.isSaveSuccessful = true;
                })
                .catch(() => {
                    this.createNotificationError({
                        message: this.$tc(
                            'sw-newsletter-recipient.detail.messageSaveError',
                            {
                                key: this.newsletterRecipient.email,
                            },
                            0,
                        ),
                    });
                });
        },

        onSaveFinish() {
            this.isSaveSuccessful = false;
            this.isLoading = false;
        },

        onCancel() {
            this.$router.push({ name: 'sw.newsletter.recipient.index' });
        },

        loadCustomFieldSets() {
            this.customFieldDataProviderService.getCustomFieldSets('newsletter_recipient').then((sets) => {
                this.customFieldSets = sets;
            });
        },
    },
};

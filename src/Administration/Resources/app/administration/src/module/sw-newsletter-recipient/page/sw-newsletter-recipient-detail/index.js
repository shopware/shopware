import template from './sw-newsletter-recipient-detail.html.twig';
import './sw-newsletter-recipient-detail.scss';

/**
 * @package buyers-experience
 */

const { Mixin } = Shopware;
const { Criteria } = Shopware.Data;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['repositoryFactory', 'acl', 'customFieldDataProviderService'],

    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('salutation'),
    ],

    data() {
        return {
            newsletterRecipient: null,
            salutations: [],
            languages: [],
            salesChannels: [],
            isLoading: false,
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
            Shopware.ExtensionAPI.publishData({
                id: 'sw-newsletter-recipient-detail__newsletterRecipient',
                path: 'newsletterRecipient',
                scope: this,
            });
            this.isLoading = true;
            const recipientCriteria = new Criteria(1, 1);

            recipientCriteria.addFilter(Criteria.equals('id', this.$route.params.id));
            recipientCriteria.addAssociation('tags');
            this.newsletterRecipientStore.search(recipientCriteria).then((newsletterRecipient) => {
                this.newsletterRecipient = newsletterRecipient.first();
                this.$nextTick(() => {
                    this.isLoading = false;
                });
            });

            this.loadCustomFieldSets();
        },

        onClickSave() {
            this.newsletterRecipientStore.save(this.newsletterRecipient, Shopware.Context.api).then(() => {
                this.createNotificationSuccess({
                    message: this.$tc(
                        'sw-newsletter-recipient.detail.messageSaveSuccess',
                        0,
                        { key: this.newsletterRecipient.email },
                    ),
                });
            });
        },

        loadCustomFieldSets() {
            this.customFieldDataProviderService.getCustomFieldSets('newsletter_recipient').then((sets) => {
                this.customFieldSets = sets;
            });
        }
    },
};

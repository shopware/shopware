import template from './sw-import-export-edit-profile-modal.html.twig';
import './sw-import-export-edit-profile-modal.scss';

const { mapApiErrors } = Shopware.Component.getComponentHelper();

/**
 * @private
 */
Shopware.Component.register('sw-import-export-edit-profile-modal', {
    template,

    props: {
        profile: {
            type: Object,
            required: false,
            default() {
                return {};
            }
        }
    },

    data() {
        return {
            supportedEntities: [
                {
                    value: 'product',
                    label: this.$tc('sw-import-export.profile.productLabel')
                },
                {
                    value: 'customer',
                    label: this.$tc('sw-import-export.profile.customerLabel')
                },
                {
                    value: 'category',
                    label: this.$tc('sw-import-export.profile.categoriesLabel')
                },
                {
                    value: 'media',
                    label: this.$tc('sw-import-export.profile.mediaLabel')
                },
                {
                    value: 'newsletter_recipient',
                    label: this.$tc('sw-import-export.profile.newsletterRecipientLabel')
                }
            ],
            supportedDelimiter: [
                {
                    value: '^',
                    label: this.$tc('sw-import-export.profile.caretsLabel')
                },
                {
                    value: ',',
                    label: this.$tc('sw-import-export.profile.commasLabel')
                },
                {
                    value: ';',
                    label: this.$tc('sw-import-export.profile.semicolonLabel')
                }
            ],
            supportedEnclosures: [
                {
                    value: '"',
                    label: this.$tc('sw-import-export.profile.doubleQuoteLabel')
                }
            ]
        };
    },

    computed: {
        ...mapApiErrors('profile',
            [
                'name',
                'sourceEntity',
                'delimiter',
                'enclosure'
            ]),

        isNew() {
            if (!this.profile || !this.profile.isNew) {
                return false;
            }

            return this.profile.isNew();
        },

        mappingLength() {
            return this.profile.mapping.length;
        },

        modalTitle() {
            return this.isNew ?
                this.$tc('sw-import-export.profile.newProfileLabel') :
                this.$tc('sw-import-export.profile.editProfileLabel');
        },

        saveLabelSnippet() {
            return this.isNew ?
                this.$tc('sw-import-export.profile.addProfileLabel') :
                this.$tc('sw-import-export.profile.saveProfileLabel');
        }
    }
});

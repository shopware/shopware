/**
 * @sw-package inventory
 */
import template from './sw-product-document-remove-modal.html.twig';
import './sw-product-document-remove-modal.scss';

/** @private */
export default Shopware.Component.wrapComponentConfig({
    template,

    inject: ['repositoryFactory'],

    props: {
        documentTitle: {
            type: String,
            required: true,
        },
        languageName: {
            type: String,
            default: '',
        },
        canEdit: {
            type: Boolean,
            default: false,
        },
        isRemovingDocument: {
            type: Boolean,
            default: false,
        },
        hasVariants: {
            type: Boolean,
            default: false,
        },
        removeFromVariants: {
            type: Boolean,
            default: false,
        },
    },

    emits: ['close', 'remove', 'update:removeFromVariants'],

    data() {
        return { fallbackLanguageName: '' };
    },

    async created() {
        if (!this.languageName) {
            return;
        }
        const language = Shopware.Store.get('context').api.language as Entity<'language'> | null;
        const fallbackLanguageId = language?.parentId || Shopware.Context.api.systemLanguageId;
        if (!fallbackLanguageId) {
            return;
        }
        const criteria = new Shopware.Data.Criteria();
        criteria.addAssociation('locale');
        const fallback = await this.repositoryFactory
            .create('language')
            .get(fallbackLanguageId, Shopware.Context.api, criteria)
            .catch(() => {
                // Only the optional language label is unavailable; confirmation and the draft reset still work.
                return null;
            });
        const code = fallback?.locale?.code.split('-')[0];
        this.fallbackLanguageName = code
            ? new Intl.DisplayNames([Shopware.Store.get('session').currentLocale || 'en-GB'], { type: 'language' }).of(
                  code,
              ) || ''
            : fallback?.name || '';
    },
});

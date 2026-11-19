/**
 * @sw-package inventory
 */
import { inject, ref } from 'vue';

/** @private */
export default function useDocumentDefaultLanguage() {
    const context = Shopware.Store.get('context');
    const repositoryFactory = inject('repositoryFactory', () => Shopware.Service('repositoryFactory'), true);
    const defaultLanguageName = ref('');
    const { systemLanguageId } = context.api;

    if (systemLanguageId && !context.isSystemDefaultLanguage) {
        void repositoryFactory
            .create('language')
            .get(systemLanguageId)
            .catch(() => {
                // The fallback label still switches to the known system language ID.
                return null;
            })
            .then((language) => {
                defaultLanguageName.value = language?.name ?? '';
            });
    }

    return { defaultLanguageName };
}

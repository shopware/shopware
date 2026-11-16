/**
 * @sw-package inventory
 */
import { inject, ref, watch } from 'vue';
import { storeToRefs } from 'pinia';

/** @private */
export default function useDocumentDefaultLanguage(enabled: () => boolean = () => true) {
    const context = Shopware.Store.get('context');
    const repositoryFactory = inject('repositoryFactory', () => Shopware.Service('repositoryFactory'), true);
    const defaultLanguageName = ref<string | null>(null);
    const { isSystemDefaultLanguage: isDefaultLanguage } = storeToRefs(context);

    watch(
        () => (enabled() && !isDefaultLanguage.value ? context.api.systemLanguageId : null),
        async (languageId) => {
            if (!languageId || defaultLanguageName.value !== null) {
                return;
            }

            const language = await repositoryFactory
                .create('language')
                .get(languageId)
                .catch(() => {
                    // The fallback label still switches to the known system language ID.
                    return null;
                });
            defaultLanguageName.value = language?.name || '';
        },
        { immediate: true },
    );

    return { defaultLanguageName, isDefaultLanguage };
}

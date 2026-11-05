/**
 * @sw-package inventory
 */

/** @private */
export function resetDocumentTranslation(
    document: Entity<'product_document'>,
    language: { parentId?: string | null } | null,
    systemLanguageId: string | null,
) {
    const fallbackLanguages = [language?.parentId, systemLanguageId];
    const translations = fallbackLanguages.flatMap(
        (languageId) => document.translations?.filter((translation) => translation.languageId === languageId) || [],
    );
    return {
        title: null,
        mediaId: null,
        translated: {
            ...document.translated,
            title: translations.find((translation) => translation.title !== null)?.title ?? null,
            mediaId: translations.find((translation) => translation.mediaId !== null)?.mediaId ?? null,
        },
    };
}

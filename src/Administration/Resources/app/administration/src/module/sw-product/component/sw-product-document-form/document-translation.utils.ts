/**
 * @sw-package inventory
 */

/** @private */
export function resetDocumentTranslation(
    document: Entity<'product_document'>,
    language: { parentId?: string | null } | null,
) {
    const fallbackLanguages = [language?.parentId, Shopware.Context.api.systemLanguageId];
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

/** @private */
export type DocumentChanges = {
    title?: string | null;
    mediaId?: EntityKey<'media'> | null;
    translated?: Partial<ReturnType<typeof resetDocumentTranslation>['translated']>;
};

/** @private */
export function getDocumentTranslationChanges(
    document: Entity<'product_document'>,
    changes: DocumentChanges,
    language: { parentId?: string | null } | null,
    useTranslatedValues = false,
): DocumentChanges {
    const result: DocumentChanges = {};
    const original = useTranslatedValues
        ? {
              title: (document.title ?? document.translated?.title ?? '').trim(),
              mediaId: document.mediaId || document.translated?.mediaId,
          }
        : document;
    if (changes.title !== undefined && changes.title !== (original.title ?? null)) {
        result.title = changes.title;
    }
    if (changes.mediaId !== undefined && changes.mediaId !== (original.mediaId ?? null)) {
        result.mediaId = changes.mediaId;
    }
    const resetFields = (['title', 'mediaId'] as const).filter((field) => result[field] === null);
    if (resetFields.length) {
        const fallback = resetDocumentTranslation(document, language).translated;
        result.translated = {
            ...document.translated,
            ...Object.fromEntries(resetFields.map((field) => [field, fallback[field]])),
        };
    }
    return result;
}

/** @private */
export function getEditChanges(
    document: Entity<'product_document'>,
    editor: {
        documentTitle: string;
        replacementMedia: Pick<Entity<'media'>, 'id'> | null;
        editingMedia?: Pick<Entity<'media'>, 'id'>;
        documentFileRemoved: boolean;
        isDefaultLanguage: boolean;
    },
    displayTitle: string,
    language: { parentId?: string | null } | null,
) {
    const title = editor.documentTitle.trim();
    const titleChanged = !editor.isDefaultLanguage || title !== displayTitle.trim();
    const changes = getDocumentTranslationChanges(
        document,
        {
            title: titleChanged ? title || null : undefined,
            mediaId: editor.replacementMedia?.id ?? (editor.documentFileRemoved ? null : undefined),
        },
        language,
    );
    return {
        changes,
        variantChanges: editor.isDefaultLanguage ? { title, mediaId: editor.editingMedia?.id } : changes,
    };
}

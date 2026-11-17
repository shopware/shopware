/**
 * @sw-package inventory
 */

/** @private */
export function getDocumentTranslationResetChanges(
    document: Entity<'product_document'>,
    language: { parentId?: string | null } | null,
    systemLanguageId: string | null,
) {
    const fallbackLanguageIds = [language?.parentId, systemLanguageId];
    const translations = fallbackLanguageIds.flatMap(
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
    translated?: Partial<ReturnType<typeof getDocumentTranslationResetChanges>['translated']>;
};

/** @private */
export function getDocumentTranslationChanges(
    document: Entity<'product_document'>,
    changes: DocumentChanges,
    context: Pick<typeof Shopware.Context.api, 'language' | 'systemLanguageId'>,
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
        const fallback = getDocumentTranslationResetChanges(document, context.language, context.systemLanguageId).translated;
        result.translated = {
            ...document.translated,
            ...Object.fromEntries(resetFields.map((field) => [field, fallback[field]])),
        };
    }
    return result;
}

/** @private */
export function getDocumentEditChanges(
    document: Entity<'product_document'>,
    editor: {
        documentTitle: string;
        replacementMedia: Pick<Entity<'media'>, 'id'> | null;
        editingMedia?: Pick<Entity<'media'>, 'id'>;
        isDocumentFileRemoved: boolean;
        isDefaultLanguage: boolean;
    },
    displayTitle: string,
    context: Pick<typeof Shopware.Context.api, 'language' | 'systemLanguageId'>,
) {
    const title = editor.documentTitle.trim();
    const shouldUpdateTitle = !editor.isDefaultLanguage || title !== displayTitle.trim();
    const changes = getDocumentTranslationChanges(
        document,
        {
            title: shouldUpdateTitle ? title || null : undefined,
            mediaId: editor.replacementMedia?.id ?? (editor.isDocumentFileRemoved ? null : undefined),
        },
        context,
    );
    return {
        changes,
        variantChanges: editor.isDefaultLanguage ? { title, mediaId: editor.editingMedia?.id } : changes,
    };
}

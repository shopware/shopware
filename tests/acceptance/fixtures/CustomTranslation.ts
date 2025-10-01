import {
    test as base,
    LanguageHelper,
    TranslationKey,
    TranslateFn,
    BUNDLED_RESOURCES,
    baseNamespaces,
} from '@shopware-ag/acceptance-test-suite';
import { LOCALE_RESOURCES, enNamespaces } from '../locales';

// Merge base BUNDLED_RESOURCES with custom LOCALE_RESOURCES
const MERGED_RESOURCES = {
    en: { ...BUNDLED_RESOURCES.en, ...LOCALE_RESOURCES.en },
    de: { ...BUNDLED_RESOURCES.de, ...LOCALE_RESOURCES.de },
} as const;

// Simple merge of base and custom namespaces
const mergedNamespaces = {
    ...baseNamespaces,
    ...enNamespaces,
} as const;

type CustomTranslationKey = TranslationKey<typeof mergedNamespaces>;

interface CustomTranslateFixture {
    Translate: TranslateFn<CustomTranslationKey>;
}

export const test = base.extend<CustomTranslateFixture>({
    Translate: async ({}, use) => {
        // Simple language detection matching playwright config
        // Priority: lang (cmd line) -> LANGUAGE -> LANG (system) -> fallback to 'en'
        let lang = process.env.lang || process.env.LANGUAGE || process.env.LANG || 'en';
        let language = lang.split(/[_.-]/)[0].toLowerCase();

        // Check if translation resources available, fallback to 'en'
        if (!MERGED_RESOURCES[language as keyof typeof MERGED_RESOURCES]) {
            console.warn(
                `⚠️  Translation resources for '${language}' not available. Supported: ${Object.keys(
                    MERGED_RESOURCES
                ).join(', ')}. Falling back to 'en'.`
            );
            language = 'en';
        }

        // Debug logging
        console.info(`🌍 Translation system initialized with language: '${language}'`);
        console.info(
            `🔍 Env vars - LANG: '${process.env.LANG}', LANGUAGE: '${process.env.LANGUAGE}', lang: '${process.env.lang}'`
        );
        console.info(`📝 Available languages: ${Object.keys(MERGED_RESOURCES).join(', ')}`);

        const languageHelper = await LanguageHelper.createInstance(
            language,
            MERGED_RESOURCES as unknown as typeof BUNDLED_RESOURCES
        );

        const translate: TranslateFn<CustomTranslationKey> = (key, options) => {
            return languageHelper.translate(key as TranslationKey, options);
        };

        await use(translate);
    },
});

export * from '@shopware-ag/acceptance-test-suite';

export type { CustomTranslationKey };

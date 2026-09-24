/**
 * @sw-package framework
 */
import { createI18n } from 'vue-i18n';
import type * as LegacySyntax from './i18n-legacy-syntax.helper';

// The vue-i18n 8 argument order is intentionally not covered by the vue-i18n 10 types.
type Translate = (this: unknown, ...args: unknown[]) => string;

function createGlobalT(): Translate {
    return createI18n({
        legacy: false,
        locale: 'en-GB',
        messages: {
            'en-GB': {
                greeting: 'Hello {name}',
                items: 'no items | one item for {name} | {count} items for {name}',
                upload: '{count} of {total} files uploaded',
            },
        },
    }).global.t as unknown as Translate;
}

const component = { $options: { name: 'sw-test-component' } };

describe('src/core/helper/i18n-legacy-syntax.helper', () => {
    let warnSpy: jest.SpyInstance;
    let createTranslate: typeof LegacySyntax.createTranslate;
    let createDeprecatedTc: typeof LegacySyntax.createDeprecatedTc;

    beforeEach(async () => {
        // Fresh module per test, so the deduplicated warnings of one test do not leak into the next one.
        jest.resetModules();
        ({ createTranslate, createDeprecatedTc } = await import('./i18n-legacy-syntax.helper'));
        warnSpy = jest.spyOn(console, 'warn').mockImplementation(() => {});
    });

    afterEach(() => {
        warnSpy.mockRestore();
    });

    describe('createTranslate', () => {
        it('translates with the vue-i18n 10 argument order without a warning', () => {
            const t = createTranslate(createGlobalT());

            expect(t.call(component, 'greeting', { name: 'Ada' })).toBe('Hello Ada');
            expect(t.call(component, 'items', { name: 'Ada' }, 2)).toBe('2 items for Ada');
            expect(warnSpy).not.toHaveBeenCalled();
        });

        it('keeps the named parameters of calls in the vue-i18n 8 argument order and warns once per call site', () => {
            const t = createTranslate(createGlobalT());

            expect(t.call(component, 'items', 1, { name: 'Ada' })).toBe('one item for Ada');
            expect(t.call(component, 'items', 3, { name: 'Ada' })).toBe('3 items for Ada');
            expect(t.call(component, 'upload', 2, { count: 2, total: 5 })).toBe('2 of 5 files uploaded');

            expect(warnSpy).toHaveBeenCalledTimes(2);
            expect(warnSpy).toHaveBeenCalledWith(
                '[Deprecation]',
                expect.stringContaining(
                    "$t('items', plural, namedParameters) uses the vue-i18n 8 argument order " +
                        'in component "sw-test-component". Use $t(\'items\', namedParameters, plural) instead.',
                ),
            );
        });

        it('warns separately per API for the same snippet key', () => {
            const globalT = createGlobalT();

            createTranslate(globalT)('upload', 2, { count: 2, total: 5 });
            createTranslate(globalT, 'Shopware.Snippet.t')('upload', 2, { count: 2, total: 5 });

            expect(warnSpy).toHaveBeenCalledTimes(2);
            expect(warnSpy).toHaveBeenCalledWith(
                '[Deprecation]',
                expect.stringContaining("Use Shopware.Snippet.t('upload', namedParameters, plural) instead."),
            );
        });

        it('passes vue-i18n 10 translate options after the plural count through unchanged', () => {
            const t = createTranslate(createGlobalT());

            expect(t('items', 2, { named: { name: 'Ada' } })).toBe('2 items for Ada');
            expect(warnSpy).not.toHaveBeenCalled();
        });
    });

    describe('createDeprecatedTc', () => {
        it('translates like $t and names the replacement in a deduplicated warning', () => {
            const tc = createDeprecatedTc(createGlobalT(), '$tc', '$t');

            expect(tc.call(component, 'greeting', { name: 'Ada' })).toBe('Hello Ada');
            expect(tc.call(component, 'greeting', { name: 'Grace' })).toBe('Hello Grace');

            expect(warnSpy).toHaveBeenCalledTimes(1);
            expect(warnSpy).toHaveBeenCalledWith(
                '[Deprecation]',
                expect.stringContaining(
                    "$tc() is deprecated and will be removed in v6.9.0. Replace $tc('greeting') with $t('greeting') " +
                        'in component "sw-test-component".',
                ),
            );
        });

        it('warns separately for every component using the same key', () => {
            const tc = createDeprecatedTc(createGlobalT(), '$tc', '$t');

            tc.call({ $options: { name: 'sw-first' } }, 'upload', { count: 1, total: 1 });
            tc.call({ $options: { name: 'sw-second' } }, 'upload', { count: 1, total: 1 });

            expect(warnSpy).toHaveBeenCalledTimes(2);
        });

        it('supports the vue-i18n 8 plural signature of $tc', () => {
            const tc = createDeprecatedTc(createGlobalT(), 'Shopware.Snippet.tc', 'Shopware.Snippet.t');

            expect(tc('items', 3, { name: 'Ada' })).toBe('3 items for Ada');
            expect(warnSpy).toHaveBeenCalledWith(
                '[Deprecation]',
                expect.stringContaining("Replace Shopware.Snippet.tc('items') with Shopware.Snippet.t('items')."),
            );
            expect(warnSpy).toHaveBeenCalledWith(
                '[Deprecation]',
                expect.stringContaining("Use Shopware.Snippet.t('items', namedParameters, plural) instead."),
            );
        });

        it('does not warn in production builds', () => {
            const nodeEnv = process.env.NODE_ENV;
            process.env.NODE_ENV = 'production';

            try {
                const tc = createDeprecatedTc(createGlobalT(), '$tc', '$t');

                expect(tc.call(component, 'items', 0)).toBe('no items');
                expect(warnSpy).not.toHaveBeenCalled();
            } finally {
                process.env.NODE_ENV = nodeEnv;
            }
        });
    });
});

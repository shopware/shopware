import {
    anchorLanguageId,
    resolveTranslatableEntry,
    resolveTranslatableFieldValue,
    withLanguageEntry,
} from '../element-settings.util';
import { stringProperty } from './fixtures';

const ANCHOR_LANGUAGE_ID = '2fbb5fe2e29a4d70aa5854ce7ce3e20b';
const GERMAN_LANGUAGE_ID = 'a1b2c3d4e5f60718293a4b5c6d7e8f90';
const FRENCH_LANGUAGE_ID = 'c3d4e5f60718293a4b5c6d7e8f90a1b2';
const ITALIAN_LANGUAGE_ID = 'd4e5f60718293a4b5c6d7e8f90a1b2c3';

describe('element settings translations', () => {
    it('returns the selected language entry', () => {
        expect(
            resolveTranslatableEntry(
                {
                    [FRENCH_LANGUAGE_ID]: 'Bonjour',
                    [ANCHOR_LANGUAGE_ID]: 'Hello',
                },
                FRENCH_LANGUAGE_ID,
            ),
        ).toBe('Bonjour');
    });

    it('keeps a missing selected language empty even when parent and system entries exist', () => {
        expect(
            resolveTranslatableEntry(
                {
                    [ANCHOR_LANGUAGE_ID]: 'Hello',
                    [GERMAN_LANGUAGE_ID]: 'Hallo',
                },
                FRENCH_LANGUAGE_ID,
            ),
        ).toBeUndefined();
    });

    it('returns undefined when only an unrelated language has a value', () => {
        expect(resolveTranslatableEntry({ [ITALIAN_LANGUAGE_ID]: 'Ciao' }, FRENCH_LANGUAGE_ID)).toBeUndefined();
    });

    it('returns undefined for an undefined value', () => {
        expect(resolveTranslatableEntry(undefined, FRENCH_LANGUAGE_ID)).toBeUndefined();
    });

    it('shows the entry of a present language map on a translatable string property', () => {
        expect(resolveTranslatableFieldValue(stringProperty, { [ANCHOR_LANGUAGE_ID]: 'Hello' }, ANCHOR_LANGUAGE_ID)).toBe(
            'Hello',
        );
    });

    it('shows an empty string instead of another language or the declared default', () => {
        expect(
            resolveTranslatableFieldValue(
                { ...stringProperty, default: 'Default' },
                { [ANCHOR_LANGUAGE_ID]: 'Hello', [GERMAN_LANGUAGE_ID]: 'Hallo' },
                FRENCH_LANGUAGE_ID,
            ),
        ).toBe('');
    });

    it('shows an absent text property as blank without applying the declared default', () => {
        expect(resolveTranslatableFieldValue({ ...stringProperty, default: 'Default' }, undefined, FRENCH_LANGUAGE_ID)).toBe(
            '',
        );
    });

    it('shows a missing numeric translation as null without applying the declared default', () => {
        expect(
            resolveTranslatableFieldValue(
                { ...stringProperty, type: 'integer', default: 3 },
                { [ITALIAN_LANGUAGE_ID]: 2 },
                FRENCH_LANGUAGE_ID,
            ),
        ).toBeNull();
    });

    it('shows a missing boolean translation as off without applying the declared default', () => {
        expect(
            resolveTranslatableFieldValue(
                { ...stringProperty, type: 'boolean', default: true },
                { [ANCHOR_LANGUAGE_ID]: true },
                FRENCH_LANGUAGE_ID,
            ),
        ).toBe(false);
    });

    it.each([
        [
            'boolean',
            false,
            true,
        ],
        [
            'number',
            3,
            12,
        ],
        ['zero', 0, 12],
        ['empty string', '', 'Default'],
    ])('returns the selected language %s entry verbatim', (_label, selectedEntry, otherEntry) => {
        expect(
            resolveTranslatableEntry(
                {
                    [ANCHOR_LANGUAGE_ID]: otherEntry,
                    [GERMAN_LANGUAGE_ID]: selectedEntry,
                },
                GERMAN_LANGUAGE_ID,
            ),
        ).toBe(selectedEntry);
    });

    it('resolves a map mixing string and boolean entries per language', () => {
        const value = {
            [ANCHOR_LANGUAGE_ID]: true,
            [GERMAN_LANGUAGE_ID]: 'ja',
        };

        expect(resolveTranslatableEntry(value, GERMAN_LANGUAGE_ID)).toBe('ja');
        expect(resolveTranslatableEntry(value, ANCHOR_LANGUAGE_ID)).toBe(true);
    });

    it.each([
        [
            'a null entry',
            null,
        ],
        [
            'an object entry',
            { value: 'Hello' },
        ],
        [
            'an array entry',
            ['Hello'],
        ],
    ])('throws when the language map carries %s', (_label, entry) => {
        expect(() =>
            resolveTranslatableEntry(
                {
                    [ANCHOR_LANGUAGE_ID]: 'Hello',
                    [GERMAN_LANGUAGE_ID]: entry,
                },
                FRENCH_LANGUAGE_ID,
            ),
        ).toThrow(/must be undefined or a non-empty language map of primitive values/);
    });

    it('exposes the default editor language', () => {
        expect(anchorLanguageId()).toBe(ANCHOR_LANGUAGE_ID);
    });

    it.each([
        [
            'a bare string',
            'Hello',
        ],
        [
            'a list',
            ['Hello'],
        ],
    ])('throws when resolving %s instead of a language map', (_label, value) => {
        expect(() => resolveTranslatableEntry(value, FRENCH_LANGUAGE_ID)).toThrow(
            /must be undefined or a non-empty language map of primitive values/,
        );
    });

    it('sets the entry of the given language and keeps every other entry', () => {
        const current = {
            [ANCHOR_LANGUAGE_ID]: 'Hello',
            [FRENCH_LANGUAGE_ID]: 'Bonjour',
        };

        expect(withLanguageEntry(current, GERMAN_LANGUAGE_ID, 'Hallo')).toEqual({
            [ANCHOR_LANGUAGE_ID]: 'Hello',
            [FRENCH_LANGUAGE_ID]: 'Bonjour',
            [GERMAN_LANGUAGE_ID]: 'Hallo',
        });
        expect(current).toEqual({
            [ANCHOR_LANGUAGE_ID]: 'Hello',
            [FRENCH_LANGUAGE_ID]: 'Bonjour',
        });
    });

    it('writes the anchor entry into a language map that has none and keeps every other entry', () => {
        expect(
            withLanguageEntry(
                {
                    [GERMAN_LANGUAGE_ID]: 'Hallo',
                    [FRENCH_LANGUAGE_ID]: 'Bonjour',
                },
                ANCHOR_LANGUAGE_ID,
                'Hello',
            ),
        ).toEqual({
            [GERMAN_LANGUAGE_ID]: 'Hallo',
            [FRENCH_LANGUAGE_ID]: 'Bonjour',
            [ANCHOR_LANGUAGE_ID]: 'Hello',
        });
    });

    it.each([
        [
            'boolean',
            false,
        ],
        [
            'number',
            0,
        ],
    ])('sets a %s entry of the given language and keeps every other entry', (_label, entry) => {
        expect(
            withLanguageEntry(
                {
                    [ANCHOR_LANGUAGE_ID]: true,
                    [FRENCH_LANGUAGE_ID]: 'oui',
                },
                GERMAN_LANGUAGE_ID,
                entry,
            ),
        ).toEqual({
            [ANCHOR_LANGUAGE_ID]: true,
            [FRENCH_LANGUAGE_ID]: 'oui',
            [GERMAN_LANGUAGE_ID]: entry,
        });
    });

    it('throws when writing over a language map carrying a null entry', () => {
        expect(() =>
            withLanguageEntry(
                {
                    [ANCHOR_LANGUAGE_ID]: 'Hello',
                    [GERMAN_LANGUAGE_ID]: null,
                },
                ANCHOR_LANGUAGE_ID,
                'Hello again',
            ),
        ).toThrow(/must be undefined or a non-empty language map of primitive values/);
    });

    it('writes a single-entry language map over an undefined current value', () => {
        expect(withLanguageEntry(undefined, ANCHOR_LANGUAGE_ID, 'Hello again')).toEqual({
            [ANCHOR_LANGUAGE_ID]: 'Hello again',
        });
    });

    it.each([
        [
            'a bare string',
            'Hello',
        ],
        [
            'a list',
            ['Hello'],
        ],
        [
            'null',
            null,
        ],
    ])('throws when writing over %s instead of a language map', (_label, current) => {
        expect(() => withLanguageEntry(current, ANCHOR_LANGUAGE_ID, 'Hello again')).toThrow(
            /must be undefined or a non-empty language map of primitive values/,
        );
    });
});

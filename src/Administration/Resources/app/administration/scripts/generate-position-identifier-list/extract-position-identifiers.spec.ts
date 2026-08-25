import fs from 'fs';
import { extractPositionIdentifiers } from './extract-position-identifiers';

jest.mock('fs');

/**
 * Feeds `extractPositionIdentifiers` file contents by path and returns the identifiers it found.
 */
function extractFrom(files: Record<string, string>): string[] {
    jest.spyOn(fs, 'readFileSync').mockImplementation((filePath) => files[filePath as string]);

    return extractPositionIdentifiers(Object.keys(files));
}

describe('extract-position-identifiers', () => {
    it.each([
        [
            'several identifiers from a twig template',
            {
                'a.html.twig': `<sw-extension-component-section position-identifier="sw-outer-section" />
                    <sw-extension-component-section position-identifier="sw-inner-section" />`,
            },
            [
                'sw-outer-section',
                'sw-inner-section',
            ],
        ],
        [
            'an identifier spread over multiple attribute lines in a vue template',
            {
                'a.vue': `<template>
                    <sw-extension-component-section
                        position-identifier="sw-native-section"
                        :data="$dataScope"
                    />
                </template>`,
            },
            ['sw-native-section'],
        ],
        [
            'both dialects from one scan',
            {
                'a.html.twig': '<div position-identifier="sw-legacy-section"></div>',
                'b.vue': '<template><div position-identifier="sw-native-section"></div></template>',
            },
            [
                'sw-legacy-section',
                'sw-native-section',
            ],
        ],
    ])('collects %s', (_case, files, expected) => {
        expect(extractFrom(files)).toEqual(expected);
    });

    it.each([
        [
            'an empty identifier',
            '<sw-extension-component-section\n    position-identifier=""\n/>',
        ],
        [
            'a null identifier',
            '<sw-extension-component-section\n    position-identifier="null"\n/>',
        ],
        [
            'a template without any identifier',
            '<template><div class="sw-card"></div></template>',
        ],
    ])('ignores %s', (_case, template) => {
        expect(extractFrom({ 'a.vue': template })).toEqual([]);
    });
});

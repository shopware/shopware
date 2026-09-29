import { extractPositionIdentifiers } from './extract-position-identifiers';

describe('extract-position-identifiers', () => {
    it.each([
        [
            'several identifiers from a twig template',
            `<sw-extension-component-section position-identifier="sw-outer-section" />
            <sw-extension-component-section position-identifier="sw-inner-section" />`,
            [
                'sw-outer-section',
                'sw-inner-section',
            ],
        ],
        [
            'an identifier spread over multiple attribute lines in a vue template',
            `<template>
                <sw-extension-component-section
                    position-identifier="sw-native-section"
                    :data="$dataScope"
                />
            </template>`,
            ['sw-native-section'],
        ],
    ])('collects %s', (_case, code, expected) => {
        expect(extractPositionIdentifiers(code)).toEqual(expected);
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
    ])('ignores %s', (_case, code) => {
        expect(extractPositionIdentifiers(code)).toEqual([]);
    });
});

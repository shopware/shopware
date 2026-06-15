/**
 * @sw-package framework
 */

import { AttributeParser } from './attribute-parser';

describe('build/vue-setup-transform/utils/attribute-parser', () => {
    it('preserves raw attribute source and offsets for quoted, unquoted, and boolean attributes', () => {
        const attributes = AttributeParser.parse(
            ' setup lang="ts" sw-component=\'sw-example\' generic=T future-flag',
            10,
        );
        
        const result = attributes.getAll();

        expect(result).toEqual([
            {
                name: 'setup',
                value: true,
                quoted: false,
                hasValue: false,
                index: 11,
                start: 1,
                end: 6,
                source: 'setup',
            },
            {
                name: 'lang',
                value: 'ts',
                quoted: true,
                hasValue: true,
                index: 17,
                start: 7,
                end: 16,
                source: 'lang="ts"',
            },
            {
                name: 'sw-component',
                value: 'sw-example',
                quoted: true,
                hasValue: true,
                index: 27,
                start: 17,
                end: 42,
                source: "sw-component='sw-example'",
            },
            {
                name: 'generic',
                value: 'T',
                quoted: false,
                hasValue: true,
                index: 53,
                start: 43,
                end: 52,
                source: 'generic=T',
            },
            {
                name: 'future-flag',
                value: true,
                quoted: false,
                hasValue: false,
                index: 63,
                start: 53,
                end: 64,
                source: 'future-flag',
            },
        ]);
    });

    it('rejects attributes with escape syntax before slicing values', () => {
        const parseAttributes = () => AttributeParser.parse(' sw-component="sw\\-example"', 10);

        expect(parseAttributes).toThrow(
            'Backslashes are not supported in Shopware setup script attributes.',
        );
    });

    it('rejects unclosed quoted attribute values', () => {
        const parseAttributes = () => AttributeParser.parse(' sw-component="sw-example', 10);

        expect(parseAttributes).toThrow(
            'Unclosed Vue SFC attribute value.',
        );
    });
});

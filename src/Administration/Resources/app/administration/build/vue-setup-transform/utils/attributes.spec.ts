/**
 * @sw-package framework
 */

import { AttributeParser } from './attribute-parser';
import { Attributes } from './attributes';

describe('build/vue-setup-transform/utils/attributes', () => {
    it('removes transform-only attributes while preserving passthrough source order', () => {
        const attributes = AttributeParser.parse(
            ' setup lang="ts" sw-component="sw-example" generic="T" future-flag',
            10,
        );

        const result = attributes.toSourceWithout([
            'sw-component',
        ]);

        expect(result).toBe(' setup lang="ts" generic="T" future-flag');
    });

    it('adds a fallback language after setup when no language attribute exists', () => {
        const attributes = AttributeParser.parse(
            ' setup sw-component="sw-example" generic="T" future-flag',
            10,
        );

        const result = attributes.toSourceWithoutEnsuringLanguage([
            'sw-component',
        ], 'ts');

        expect(result).toBe(' setup lang="ts" generic="T" future-flag');
    });

    it('keeps an existing language attribute when ensuring a fallback language', () => {
        const attributes = AttributeParser.parse(
            ' setup lang="js" sw-component="sw-example" generic="T"',
            10,
        );

        const result = attributes.toSourceWithoutEnsuringLanguage([
            'sw-component',
        ], 'ts');

        expect(result).toBe(' setup lang="js" generic="T"');
    });

    it('detects static and bound Shopware setup mode attributes', () => {
        const staticAttributes = new Attributes([
            {
                name: 'sw-component',
                value: 'sw-example',
                quoted: true,
                hasValue: true,
                index: 0,
                start: 0,
                end: 25,
                source: 'sw-component="sw-example"',
            },
        ]);
        const boundAttributes = new Attributes([
            {
                name: ':sw-component',
                value: 'componentName',
                quoted: true,
                hasValue: true,
                index: 0,
                start: 0,
                end: 29,
                source: ':sw-component="componentName"',
            },
        ]);

        const hasShopwareSetupModeAttribute = staticAttributes.hasShopwareSetupModeAttribute();
        const hasBoundAttributes = boundAttributes.hasBoundAttributes();
        const isBoundOverride = Attributes.isBound('v-bind:sw-override', 'sw-override');

        expect(hasShopwareSetupModeAttribute).toBe(true);
        expect(hasBoundAttributes).toBe(true);
        expect(isBoundOverride).toBe(true);
    });
});


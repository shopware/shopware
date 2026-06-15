/**
 * @sw-package framework
 */

import { AttributeParser } from './attribute-parser';
import { normalizeShopwareSetupBlock } from './shopware-setup-block';

describe('build/vue-setup-transform/utils/shopware-setup-block', () => {
    it('returns null for plain script setup blocks without Shopware mode attributes', () => {
        const block = createScriptBlock(' setup lang="ts"');
        const result = normalizeShopwareSetupBlock(block);

        expect(result).toBeNull();
    });

    it('normalizes base and override mode blocks with supported script languages', () => {
        const baseBlock = createScriptBlock(' setup lang="tsx" sw-component="sw-example"');
        const overrideBlock = createScriptBlock(' setup sw-override="sw-example"');

        const base = normalizeShopwareSetupBlock(baseBlock);
        const override = normalizeShopwareSetupBlock(overrideBlock);

        expect(base).toMatchObject({
            mode: 'base',
            componentName: 'sw-example',
            lang: 'tsx',
        });
        expect(override).toMatchObject({
            mode: 'override',
            componentName: 'sw-example',
            lang: null,
        });
    });

    it('rejects bound, empty, unquoted, and conflicting mode attributes', () => {
        const normalizeBoundMode = () =>
            normalizeShopwareSetupBlock(createScriptBlock(' setup :sw-component="componentName"'));
        const normalizeEmptyMode = () => normalizeShopwareSetupBlock(createScriptBlock(' setup sw-component=""'));
        const normalizeUnquotedMode = () =>
            normalizeShopwareSetupBlock(createScriptBlock(' setup sw-component=sw-example'));
        const normalizeConflictingMode = () =>
            normalizeShopwareSetupBlock(
                createScriptBlock(' setup sw-component="sw-example" sw-override="sw-example"'),
            );

        expect(normalizeBoundMode).toThrow(
            'Shopware setup mode attributes must be static strings, not bound expressions.',
        );
        expect(normalizeEmptyMode).toThrow(
            'The sw-component attribute must not be empty.',
        );
        expect(normalizeUnquotedMode).toThrow(
            'The sw-component attribute must use a static quoted string value.',
        );
        expect(normalizeConflictingMode).toThrow('Use either sw-component or sw-override on a Shopware setup block, not both.');
    });

    it('rejects unsupported or non-static lang attributes', () => {
        const normalizeUnsupportedLanguage = () =>
            normalizeShopwareSetupBlock(createScriptBlock(' setup lang="coffee" sw-component="sw-example"'));
        const normalizeUnquotedLanguage = () =>
            normalizeShopwareSetupBlock(createScriptBlock(' setup lang=ts sw-component="sw-example"'));

        expect(normalizeUnsupportedLanguage).toThrow(
            'Unsupported Shopware setup script language "coffee". Supported languages are js, jsx, ts, and tsx.',
        );
        expect(normalizeUnquotedLanguage).toThrow(
            'The lang attribute on a Shopware setup block must be a static quoted value.',
        );
    });
});

/**
 * @param {string} attrsSource
 * @returns {import('./sfc-script-block').ScriptBlock}
 */
function createScriptBlock(attrsSource) {
    return {
        type: 'scriptSetup',
        start: 0,
        end: attrsSource.length + '<script></script>'.length,
        contentStart: attrsSource.length + '<script'.length + 1,
        content: '',
        attributes: AttributeParser.parse(attrsSource, '<script'.length),
        passthroughAttributesSource: '',
    };
}

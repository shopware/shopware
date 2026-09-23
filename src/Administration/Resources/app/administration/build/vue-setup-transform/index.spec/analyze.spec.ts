/**
 * @sw-package framework
 */

import MagicString from 'magic-string';
import { analyzeShopwareSetupSfc, validateShopwareSetupSfc } from '../index.ts';
import { ShopwareSetupTransformError } from '../utils/transform-error';
import { stripIndent } from './helpers';

describe('build/vue-setup-transform analyzeShopwareSetupSfc', () => {
    it('accepts a valid component without generating code or a sourcemap', () => {
        const toString = jest.spyOn(MagicString.prototype, 'toString');
        const source = stripIndent`
            <script setup>
            const count = 1;
            swDefinePublic({ count });
            </script>
        `;

        expect(analyzeShopwareSetupSfc(source, 'sw-analyze.vue')).toBeUndefined();
        expect(toString).not.toHaveBeenCalled();

        toString.mockRestore();
    });

    it('throws the transform diagnostics with their source range', () => {
        const source = stripIndent`
            <script setup>
            const __swSetupThing = 1;
            swDefinePublic({});
            </script>
        `;

        let thrown: unknown;

        try {
            analyzeShopwareSetupSfc(source, 'sw-analyze.vue');
        } catch (error) {
            thrown = error;
        }

        expect(thrown).toBeInstanceOf(ShopwareSetupTransformError);
        expect((thrown as ShopwareSetupTransformError).index).toBe(source.indexOf('__swSetupThing'));
        expect((thrown as ShopwareSetupTransformError).endIndex).toBe(
            source.indexOf('__swSetupThing') + '__swSetupThing'.length,
        );
    });

    it('returns quietly for SFCs Vue itself cannot parse', () => {
        expect(analyzeShopwareSetupSfc('<template><div></template>', 'sw-broken.vue')).toBeUndefined();
    });

    it('keeps validateShopwareSetupSfc as an alias', () => {
        expect(validateShopwareSetupSfc).toBe(analyzeShopwareSetupSfc);
    });
});

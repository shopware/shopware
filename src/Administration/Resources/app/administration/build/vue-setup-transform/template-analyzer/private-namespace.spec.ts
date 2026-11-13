/**
 * @sw-package framework
 */

import { transformShopwareSetupSfc } from '../index.ts';

/**
 * Covers the override-private namespace: a module-root `Symbol()` used as a computed key.
 *
 * The symbol replaces an earlier filename+sha1 string key. What matters is not the binding's name -
 * which is fixed - but that it is declared once at module scope and referenced as a computed key in both
 * halves of the file, so each override files its locals under the shared `__swOverride` channel without
 * needing a globally unique name.
 */
describe('build/vue-setup-transform/template-analyzer private namespace', () => {
    const source = `<template>
    <sw-block extends="body">{{ info }}</sw-block>
</template>
<script setup lang="ts">
const info = 'local';
swDefineOverride({});
</script>`;

    it('declares the namespace symbol at module root, outside the setup script', () => {
        const result = transformShopwareSetupSfc(source, 'sw-thing.override.vue')?.code ?? '';

        expect(result).toContain("const __swSetupNamespace = Symbol('sw-thing.override');");

        // `<script setup>` is the body of the hidden component's setup(), so a symbol created there would
        // differ for every setup run and the block content of one run could not read the state of another.
        expect(result.indexOf('const __swSetupNamespace')).toBeLessThan(result.indexOf('<script setup'));
    });

    it('takes the namespace of a hot-updated override from the global symbol registry, keyed by the file', () => {
        const result =
            transformShopwareSetupSfc(source, 'src/deep/path/sw-thing.override.vue', { hotUpdates: true })?.code ?? '';

        expect(result).toContain(
            "const __swSetupNamespace = Symbol.for('sw-thing.override:src/deep/path/sw-thing.override.vue');",
        );
    });

    it('uses the symbol as a computed key in both the reference accessor and the returned state', () => {
        const result = transformShopwareSetupSfc(source, 'sw-thing.override.vue')?.code ?? '';

        expect(result).toContain('const __swSetupOverrideScope = (scope) => scope.__swOverride[__swSetupNamespace];');
        expect(result).toContain('{{ __swSetupOverrideScope(__swSetupScope).info }}');
        expect(result).toContain('[__swSetupNamespace]: {');
    });

    it('carries no filename or hash into the generated key', () => {
        const result = transformShopwareSetupSfc(source, 'src/deep/path/sw-thing.override.vue')?.code ?? '';

        expect(result).not.toContain('sw_thing_override');
        expect(result).not.toMatch(/__swOverride: \{ [A-Za-z_$][A-Za-z0-9_$]*_[a-f0-9]{5}:/u);
    });

    it('generates identical text for two overrides of the same component', () => {
        const first = transformShopwareSetupSfc(source, 'a/sw-thing.override.vue')?.code ?? '';
        const second = transformShopwareSetupSfc(source, 'b/sw-thing.override.vue')?.code ?? '';

        // Uniqueness is provided at runtime by Symbol(), not by the generated name: each override is its
        // own module and evaluates its own declaration, so two identical texts still yield two symbols.
        expect(first).toEqual(second);
    });
});

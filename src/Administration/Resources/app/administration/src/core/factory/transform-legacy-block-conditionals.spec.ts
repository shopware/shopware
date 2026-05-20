/**
 * @sw-package framework
 */
import { compile } from '@vue/compiler-dom';
import { transformLegacyBlockExtensionConditionals } from './transform-legacy-block-conditionals';

describe('core/factory/transform-legacy-block-conditionals.ts', () => {
    it('rewrites legacy Twig shim v-else branches after sw-block-parent', () => {
        const template = `
            <sw-block-parent />
            <div v-else class="false-branch">false</div>
        `;

        const transformedTemplate = transformLegacyBlockExtensionConditionals('test-block', template);

        expect(transformedTemplate).toContain('<sw-block-parent></sw-block-parent>');
        expect(transformedTemplate).toContain(`v-if="$swLegacyBlockElse('test-block')"`);
        expect(transformedTemplate).not.toContain('v-else class="false-branch"');
        expect(() => compile(transformedTemplate)).not.toThrow();
    });

    it('rewrites legacy Twig shim v-else-if branches after sw-block-parent', () => {
        const template = `
            <sw-block-parent />
            <div v-else-if="showRed" class="red-branch">red</div>
        `;

        const transformedTemplate = transformLegacyBlockExtensionConditionals('test-block', template);

        expect(transformedTemplate).toContain(`v-if="$swLegacyBlockElseIf('test-block', showRed)"`);
        expect(transformedTemplate).not.toContain('v-else-if="showRed"');
        expect(() => compile(transformedTemplate)).not.toThrow();
    });

    it('leaves leading v-else branches without sw-block-parent untouched', () => {
        const template = `
            <div v-else class="false-branch">false</div>
        `;

        expect(transformLegacyBlockExtensionConditionals('test-block', template)).toBe(template);
    });

    it('rewrites legacy Twig shim v-else branches after a later sw-block-parent', () => {
        const template = `
            <div class="before-parent">before</div>
            <sw-block-parent />
            <div v-else class="false-branch">false</div>
        `;

        const transformedTemplate = transformLegacyBlockExtensionConditionals('test-block', template);

        expect(transformedTemplate).toContain('<sw-block-parent></sw-block-parent>');
        expect(transformedTemplate).toContain(`v-if="$swLegacyBlockElse('test-block')"`);
        expect(transformedTemplate).not.toContain('v-else class="false-branch"');
        expect(() => compile(transformedTemplate)).not.toThrow();
    });
});

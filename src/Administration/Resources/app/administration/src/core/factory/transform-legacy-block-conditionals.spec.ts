/**
 * @sw-package framework
 */
import { compile } from '@vue/compiler-dom';
import transformLegacyBlockConditionals, {
    transformLegacyBlockExtensionConditionals,
} from './transform-legacy-block-conditionals';

describe('core/factory/transform-legacy-block-conditionals.ts', () => {
    it('rewrites legacy v-if / v-else branches across sw-block boundaries', () => {
        const template = `
            <div>
                <sw-block name="test-block">
                    <div v-if="isConditionTrue" class="true-branch">true</div>
                </sw-block>

                <sw-block extends="test-block">
                    <sw-block-parent />
                    <div v-else class="false-branch">false</div>
                </sw-block>
            </div>
        `;

        const transformedTemplate = transformLegacyBlockConditionals(template);

        expect(transformedTemplate).toContain(`v-if="$swLegacyBlockIf('test-block', isConditionTrue)"`);
        expect(transformedTemplate).toContain(`v-if="$swLegacyBlockElse('test-block')"`);
        expect(transformedTemplate).not.toContain('v-else class="false-branch"');
        expect(() => compile(transformedTemplate)).not.toThrow();
    });

    it('rewrites v-else-if chains that span native sw-block extensions', () => {
        const template = `
            <div>
                <sw-block name="test-block">
                    <div v-if="showBlue" class="blue-branch">blue</div>
                    <div v-else-if="showGreen" class="green-branch">green</div>
                </sw-block>

                <sw-block extends="test-block">
                    <sw-block-parent />
                    <div v-else-if="showRed" class="red-branch">red</div>
                </sw-block>

                <sw-block extends="test-block">
                    <sw-block-parent />
                    <div v-else class="fallback-branch">fallback</div>
                </sw-block>
            </div>
        `;

        const transformedTemplate = transformLegacyBlockConditionals(template);

        expect(transformedTemplate).toContain(`v-if="$swLegacyBlockIf('test-block', showBlue)"`);
        expect(transformedTemplate).toContain(`v-if="$swLegacyBlockElseIf('test-block', showGreen)"`);
        expect(transformedTemplate).toContain(`v-if="$swLegacyBlockElseIf('test-block', showRed)"`);
        expect(transformedTemplate).toContain(`v-if="$swLegacyBlockElse('test-block')"`);
        expect(() => compile(transformedTemplate)).not.toThrow();
    });

    it('rewrites native legacy conditionals after a later sw-block-parent', () => {
        const template = `
            <div>
                <sw-block name="test-block">
                    <div v-if="showDefault" class="default-branch">default</div>
                </sw-block>

                <sw-block extends="test-block">
                    <div class="before-parent">before</div>
                    <sw-block-parent />
                    <div v-else class="fallback-branch">fallback</div>
                </sw-block>
            </div>
        `;

        const transformedTemplate = transformLegacyBlockConditionals(template);

        expect(transformedTemplate).toContain(`v-if="$swLegacyBlockIf('test-block', showDefault)"`);
        expect(transformedTemplate).toContain(`v-if="$swLegacyBlockElse('test-block')"`);
        expect(transformedTemplate).not.toContain('v-else class="fallback-branch"');
        expect(() => compile(transformedTemplate)).not.toThrow();
    });

    it('rewrites v-else branches that continue in the following named sw-block', () => {
        const template = `
            <div>
                <sw-block name="grid-block">
                    <sw-data-grid v-if="items.length" class="grid-branch"></sw-data-grid>
                </sw-block>

                <sw-block name="empty-state-block">
                    <mt-empty-state v-else class="empty-branch"></mt-empty-state>
                </sw-block>
            </div>
        `;

        const transformedTemplate = transformLegacyBlockConditionals(template);

        expect(transformedTemplate).toContain(`v-if="$swLegacyBlockIf('grid-block', items.length)"`);
        expect(transformedTemplate).toContain(`v-if="$swLegacyBlockElse('grid-block')"`);
        expect(transformedTemplate).not.toContain(`$swLegacyBlockIf('empty-state-block'`);
        expect(transformedTemplate).not.toContain('v-else class="empty-branch"');
        expect(() => compile(transformedTemplate)).not.toThrow();
    });

    it('rewrites v-else-if / v-else chains that span multiple following named sw-blocks', () => {
        const template = `
            <div>
                <sw-block name="blue-block">
                    <div v-if="showBlue" class="blue-branch">blue</div>
                </sw-block>

                <sw-block name="green-block">
                    <div v-else-if="showGreen" class="green-branch">green</div>
                </sw-block>

                <sw-block name="fallback-block">
                    <div v-else class="fallback-branch">fallback</div>
                </sw-block>
            </div>
        `;

        const transformedTemplate = transformLegacyBlockConditionals(template);

        expect(transformedTemplate).toContain(`v-if="$swLegacyBlockIf('blue-block', showBlue)"`);
        expect(transformedTemplate).toContain(`v-if="$swLegacyBlockElseIf('blue-block', showGreen)"`);
        expect(transformedTemplate).toContain(`v-if="$swLegacyBlockElse('blue-block')"`);
        expect(transformedTemplate).not.toContain(`$swLegacyBlockIf('green-block'`);
        expect(transformedTemplate).not.toContain(`$swLegacyBlockIf('fallback-block'`);
        expect(transformedTemplate).not.toContain('v-else-if="showGreen"');
        expect(transformedTemplate).not.toContain('v-else class="fallback-branch"');
        expect(() => compile(transformedTemplate)).not.toThrow();
    });

    it('leaves unrelated templates untouched', () => {
        const template = `
            <div>
                <sw-block name="test-block">
                    <div class="default-branch">default</div>
                </sw-block>
            </div>
        `;

        expect(transformLegacyBlockConditionals(template)).toBe(template);
    });

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

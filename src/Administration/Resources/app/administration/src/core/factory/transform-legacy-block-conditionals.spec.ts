/**
 * @sw-package framework
 */
import { compile } from '@vue/compiler-dom';
import transformLegacyBlockConditionals from './transform-legacy-block-conditionals';

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
});

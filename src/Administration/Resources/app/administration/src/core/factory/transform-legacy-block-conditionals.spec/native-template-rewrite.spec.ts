/**
 * @sw-package framework
 */
import transformLegacyBlockConditionals from '../transform-legacy-block-conditionals';
import { expectTemplateCompiles, options } from './fixtures';

describe('core/factory/transform-legacy-block-conditionals.ts - native template rewrite', () => {
    it('rewrites legacy v-if / v-else cases across sw-block boundaries', () => {
        const template = `
            <div>
                <sw-block name="test-block">
                    <div v-if="isConditionTrue" class="true-case">true</div>
                </sw-block>

                <sw-block extends="test-block">
                    <sw-block-parent />
                    <div v-else class="false-case">false</div>
                </sw-block>
            </div>
        `;

        const transformedTemplate = transformLegacyBlockConditionals(template);

        expect(transformedTemplate).toContain(
            `v-if="$swLegacyBlockIf('test-block:0', isConditionTrue, ${options(0, true, 'defaultSlot')})"`,
        );
        expect(transformedTemplate).toContain(
            `v-if="$swLegacyBlockElse('test-block:0', ${options(0, false, 'nativeExtension')})"`,
        );
        expect(transformedTemplate).not.toContain('v-else class="false-case"');
        expectTemplateCompiles(transformedTemplate);
    });

    it('rewrites v-else-if chains that span native sw-block extensions', () => {
        const template = `
            <div>
                <sw-block name="test-block">
                    <div v-if="showBlue" class="blue-case">blue</div>
                    <div v-else-if="showGreen" class="green-case">green</div>
                </sw-block>

                <sw-block extends="test-block">
                    <sw-block-parent />
                    <div v-else-if="showRed" class="red-case">red</div>
                </sw-block>

                <sw-block extends="test-block">
                    <sw-block-parent />
                    <div v-else class="fallback-case">fallback</div>
                </sw-block>
            </div>
        `;

        const transformedTemplate = transformLegacyBlockConditionals(template);

        expect(transformedTemplate).toContain(
            `v-if="$swLegacyBlockIf('test-block:0', showBlue, ${options(0, true, 'defaultSlot')})"`,
        );
        expect(transformedTemplate).toContain(
            `v-if="$swLegacyBlockElseIf('test-block:0', showGreen, ${options(1, false, 'defaultSlot')})"`,
        );
        expect(transformedTemplate).toContain(
            `v-if="$swLegacyBlockElseIf('test-block:0', showRed, ${options(0, false, 'nativeExtension')})"`,
        );
        expect(transformedTemplate).toContain(
            `v-if="$swLegacyBlockElse('test-block:0', ${options(1, false, 'nativeExtension')})"`,
        );
        expectTemplateCompiles(transformedTemplate);
    });

    it('preserves escaped quotes while normalizing self-closing tags', () => {
        const template = `
            <div>
                <sw-block name="quote-block">
                    <my-component
                        attr="foo\\"bar"
                        :config="{ label: 'A/B' }"
                        v-if="showComponent"
                    />
                </sw-block>

                <sw-block extends="quote-block">
                    <sw-block-parent />
                    <div v-else class="fallback-case">fallback</div>
                </sw-block>
            </div>
        `;

        const transformedTemplate = transformLegacyBlockConditionals(template);

        expect(transformedTemplate).toContain('attr="foo\\"bar"');
        expect(transformedTemplate).toContain(`:config="{ label: 'A/B' }"`);
        expect(transformedTemplate).toContain(
            `v-if="$swLegacyBlockIf('quote-block:0', showComponent, ${options(0, true, 'defaultSlot')})"`,
        );
        expect(transformedTemplate).toContain(
            `v-if="$swLegacyBlockElse('quote-block:0', ${options(0, false, 'nativeExtension')})"`,
        );
    });

    it('rewrites quoted condition expressions without breaking the compiled template', () => {
        const template = `
            <div>
                <sw-block name="quote-expression-block">
                    <div v-if='state === "open"' class="open-case">open</div>
                </sw-block>

                <sw-block extends="quote-expression-block">
                    <sw-block-parent />
                    <div v-else class="fallback-case">fallback</div>
                </sw-block>
            </div>
        `;

        const transformedTemplate = transformLegacyBlockConditionals(template);

        expect(transformedTemplate).toContain(`$swLegacyBlockIf('quote-expression-block:0'`);
        expect(transformedTemplate).toContain(
            `$swLegacyBlockElse('quote-expression-block:0', ${options(0, false, 'nativeExtension')})`,
        );
        expect(transformedTemplate).not.toContain(`v-if='state === "open"'`);
        expectTemplateCompiles(transformedTemplate);
    });

    it('preserves camelCase props and event names while rewriting conditionals', () => {
        const template = `
            <div>
                <sw-block name="case_block">
                    <my-component
                        :lineItems="lineItems"
                        @update:modelValue="onUpdate"
                        v-if="showComponent"
                    />
                </sw-block>
            </div>
        `;

        const transformedTemplate = transformLegacyBlockConditionals(template);

        expect(transformedTemplate).toContain(':lineItems="lineItems"');
        expect(transformedTemplate).toContain('@update:modelValue="onUpdate"');
        expect(transformedTemplate).not.toContain(':lineitems="lineItems"');
        expect(transformedTemplate).not.toContain('@update:modelvalue="onUpdate"');
        expect(transformedTemplate).toContain(
            `v-if="$swLegacyBlockIf('case_block:0', showComponent, ${options(0, true, 'defaultSlot')})"`,
        );
    });

    it('rewrites native legacy conditionals after a later sw-block-parent', () => {
        const template = `
            <div>
                <sw-block name="test-block">
                    <div v-if="showDefault" class="default-case">default</div>
                </sw-block>

                <sw-block extends="test-block">
                    <div class="before-parent">before</div>
                    <sw-block-parent />
                    <div v-else class="fallback-case">fallback</div>
                </sw-block>
            </div>
        `;

        const transformedTemplate = transformLegacyBlockConditionals(template);

        expect(transformedTemplate).toContain(
            `v-if="$swLegacyBlockIf('test-block:0', showDefault, ${options(0, true, 'defaultSlot')})"`,
        );
        expect(transformedTemplate).toContain(
            `v-if="$swLegacyBlockElse('test-block:0', ${options(0, false, 'nativeExtension')})"`,
        );
        expect(transformedTemplate).not.toContain('v-else class="fallback-case"');
        expectTemplateCompiles(transformedTemplate);
    });

    it('rewrites v-else cases that continue in the following named sw-block', () => {
        const template = `
            <div>
                <sw-block name="grid-block">
                    <sw-data-grid v-if="items.length" class="grid-case"></sw-data-grid>
                </sw-block>

                <sw-block name="empty-state-block">
                    <mt-empty-state v-else class="empty-case"></mt-empty-state>
                </sw-block>
            </div>
        `;

        const transformedTemplate = transformLegacyBlockConditionals(template);

        expect(transformedTemplate).toContain(
            `v-if="$swLegacyBlockIf('grid-block:0', items.length, ${options(0, true, 'defaultSlot')})"`,
        );
        expect(transformedTemplate).toContain(
            `v-if="$swLegacyBlockElse('grid-block:0', ${options(1, false, 'defaultSlot')})"`,
        );
        expect(transformedTemplate).not.toContain(`$swLegacyBlockIf('empty-state-block'`);
        expect(transformedTemplate).not.toContain('v-else class="empty-case"');
        expectTemplateCompiles(transformedTemplate);
    });

    it('rewrites v-else-if / v-else chains that span multiple following named sw-blocks', () => {
        const template = `
            <div>
                <sw-block name="blue-block">
                    <div v-if="showBlue" class="blue-case">blue</div>
                </sw-block>

                <sw-block name="green-block">
                    <div v-else-if="showGreen" class="green-case">green</div>
                </sw-block>

                <sw-block name="fallback-block">
                    <div v-else class="fallback-case">fallback</div>
                </sw-block>
            </div>
        `;

        const transformedTemplate = transformLegacyBlockConditionals(template);

        expect(transformedTemplate).toContain(
            `v-if="$swLegacyBlockIf('blue-block:0', showBlue, ${options(0, true, 'defaultSlot')})"`,
        );
        expect(transformedTemplate).toContain(
            `v-if="$swLegacyBlockElseIf('blue-block:0', showGreen, ${options(1, false, 'defaultSlot')})"`,
        );
        expect(transformedTemplate).toContain(
            `v-if="$swLegacyBlockElse('blue-block:0', ${options(2, false, 'defaultSlot')})"`,
        );
        expect(transformedTemplate).not.toContain(`$swLegacyBlockIf('green-block'`);
        expect(transformedTemplate).not.toContain(`$swLegacyBlockIf('fallback-block'`);
        expect(transformedTemplate).not.toContain('v-else-if="showGreen"');
        expect(transformedTemplate).not.toContain('v-else class="fallback-case"');
        expectTemplateCompiles(transformedTemplate);
    });

    it('leaves unrelated templates untouched', () => {
        const template = `
            <div>
                <sw-block name="test-block">
                    <div class="default-case">default</div>
                </sw-block>
            </div>
        `;

        expect(transformLegacyBlockConditionals(template)).toBe(template);
    });

    it('leaves conditional chains in the middle of a block untouched', () => {
        const template = `
            <div>
                <sw-block name="test-block">
                    <div></div>
                    <div v-if="someCondition" class="if-branch">default</div>
                    <div v-else class="else-branch">default</div>
                    <div></div>
                </sw-block>
            </div>
        `;

        expect(transformLegacyBlockConditionals(template)).toBe(template);
    });

    it('keeps an earlier unrelated directive with the same expression untouched', () => {
        const template = `
            <div>
                <section>
                    <div v-if="item.active === true" class="unrelated-active-case">active</div>
                    <div v-else-if="item.active === false" class="unrelated-case">inactive</div>
                </section>

                <sw-block name="variant-block">
                    <div v-if="item.active === true" class="active-case">active</div>
                </sw-block>

                <sw-block extends="variant-block">
                    <sw-block-parent />
                    <div v-else-if="item.active === false" class="inactive-case">inactive</div>
                    <div v-else class="fallback-case">fallback</div>
                </sw-block>
            </div>
        `;

        const transformedTemplate = transformLegacyBlockConditionals(template);

        expect(transformedTemplate).toContain('<div v-if="item.active === true" class="unrelated-active-case">active</div>');
        expect(transformedTemplate).toContain(
            '<div v-else-if="item.active === false" class="unrelated-case">inactive</div>',
        );
        expect(transformedTemplate).toContain(
            `v-if="$swLegacyBlockElseIf('variant-block:0', item.active === false, ${options(0, false, 'nativeExtension')})" class="inactive-case"`,
        );
        expectTemplateCompiles(transformedTemplate);
    });
});

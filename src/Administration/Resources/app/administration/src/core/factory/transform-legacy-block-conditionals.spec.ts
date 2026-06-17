/**
 * @sw-package framework
 */
import { compile } from '@vue/compiler-dom';
import transformLegacyBlockConditionals, {
    transformLegacyTwigBlockSequenceConditionals,
} from './transform-legacy-block-conditionals';

function options(segmentCaseIndex: number, isStartingCondition: boolean, renderOrderSegment: string): string {
    return `{ segmentCaseIndex: ${segmentCaseIndex}, isStartingCondition: ${isStartingCondition}, renderOrderSegment: '${renderOrderSegment}' }`;
}

const TEST_COMPONENT = 'test-component';

describe('core/factory/transform-legacy-block-conditionals.ts', () => {
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
        expect(() => compile(transformedTemplate)).not.toThrow();
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
        expect(() => compile(transformedTemplate)).not.toThrow();
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
        expect(() => compile(transformedTemplate)).not.toThrow();
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
        expect(() => compile(transformedTemplate)).not.toThrow();
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
        expect(() => compile(transformedTemplate)).not.toThrow();
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
        expect(() => compile(transformedTemplate)).not.toThrow();
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

    it('keeps whitespace between rewritten v-if and following attributes', () => {
        const transformedEntries = transformLegacyTwigBlockSequenceConditionals(
            [
                {
                    blockName: 'leading-block',
                    innerTemplate: '<div v-if="showLeading" class="leading-case"></div>',
                },
                {
                    blockName: 'middle-block',
                    innerTemplate: '<div v-else-if="showMiddle" class="middle-case"></div>',
                },
                {
                    blockName: 'fallback-block',
                    innerTemplate: '<div v-else class="legacy-else"></div>',
                },
            ],
            TEST_COMPONENT,
        );

        expect(transformedEntries[1].innerTemplate).toContain(
            `v-if="$swLegacyBlockElseIf('leading-block:0', showMiddle, ${options(1, false, 'shimExtension')})" class="middle-case"`,
        );
        expect(transformedEntries[1].innerTemplate).not.toContain(`"-if="showMiddle"`);
        expect(transformedEntries[2].innerTemplate).toContain(
            `v-if="$swLegacyBlockElse('leading-block:0', ${options(2, false, 'shimExtension')})" class="legacy-else"`,
        );
        expect(transformedEntries[2].innerTemplate).not.toContain(`')"class="legacy-else"`);
    });

    it('rewrites legacy Twig shim v-else cases after sw-block-parent', () => {
        const template = `
            <sw-block-parent />
            <div v-else class="false-case">false</div>
        `;

        const [entry] = transformLegacyTwigBlockSequenceConditionals(
            [
                {
                    blockName: 'test-block',
                    innerTemplate: template,
                },
            ],
            TEST_COMPONENT,
        );

        expect(entry.innerTemplate).toContain('<sw-block-parent />');
        expect(entry.innerTemplate).toContain(
            `v-if="$swLegacyBlockElse('test-block:0', ${options(0, false, 'shimExtension')})"`,
        );
        expect(entry.innerTemplate).not.toContain('v-else class="false-case"');
        expect(entry.legacyConditionCases).toEqual([
            {
                chainKey: 'test-block:0',
                caseCount: 1,
                caseStartIndex: 0,
            },
        ]);
        expect(() => compile(entry.innerTemplate)).not.toThrow();
    });

    it('rewrites legacy Twig shim v-else-if cases after sw-block-parent', () => {
        const template = `
            <sw-block-parent />
            <div v-else-if="showRed" class="red-case">red</div>
        `;

        const [entry] = transformLegacyTwigBlockSequenceConditionals(
            [
                {
                    blockName: 'test-block',
                    innerTemplate: template,
                },
            ],
            TEST_COMPONENT,
        );

        expect(entry.innerTemplate).toContain(
            `v-if="$swLegacyBlockElseIf('test-block:0', showRed, ${options(0, false, 'shimExtension')})"`,
        );
        expect(entry.innerTemplate).not.toContain('v-else-if="showRed"');
        expect(() => compile(entry.innerTemplate)).not.toThrow();
    });

    it('rewrites legacy Twig shim v-else cases after a later sw-block-parent', () => {
        const template = `
            <div class="before-parent">before</div>
            <sw-block-parent />
            <div v-else class="false-case">false</div>
        `;

        const [entry] = transformLegacyTwigBlockSequenceConditionals(
            [
                {
                    blockName: 'test-block',
                    innerTemplate: template,
                },
            ],
            TEST_COMPONENT,
        );

        expect(entry.innerTemplate).toContain('<sw-block-parent />');
        expect(entry.innerTemplate).toContain(
            `v-if="$swLegacyBlockElse('test-block:0', ${options(0, false, 'shimExtension')})"`,
        );
        expect(entry.innerTemplate).not.toContain('v-else class="false-case"');
        expect(() => compile(entry.innerTemplate)).not.toThrow();
    });

    it('rewrites legacy Twig shim v-else-if cases from an initial chain key', () => {
        const template = `
            <div v-else-if="showSecond" class="second-case">second</div>
        `;

        const [entry] = transformLegacyTwigBlockSequenceConditionals(
            [
                {
                    blockName: 'second-block',
                    innerTemplate: template,
                },
            ],
            TEST_COMPONENT,
            { 'second-block:0': 1 },
        );

        expect(entry.innerTemplate).toContain(
            `v-if="$swLegacyBlockElseIf('second-block:0', showSecond, ${options(1, false, 'shimExtension')})"`,
        );
        expect(entry.innerTemplate).not.toContain('v-else-if="showSecond"');
        expect(entry.legacyConditionCases).toEqual([
            {
                chainKey: 'second-block:0',
                caseCount: 1,
                caseStartIndex: 1,
            },
        ]);
        expect(() => compile(entry.innerTemplate)).not.toThrow();
    });

    describe('all supported template combinations of conditional chains', () => {
        it('rewrites a native core v-if with a legacy Twig v-else extension', () => {
            const nativeTemplate = transformLegacyBlockConditionals(`
                <sw-block name="test_block">
                    <div v-if="condition">Test</div>
                </sw-block>
            `);
            const [extensionEntry] = transformLegacyTwigBlockSequenceConditionals(
                [
                    {
                        blockName: 'test_block',
                        innerTemplate: `
                            <sw-block-parent />
                            <div v-else>Test else</div>
                        `,
                    },
                ],
                TEST_COMPONENT,
            );

            expect(nativeTemplate).toContain(
                `v-if="$swLegacyBlockIf('test_block:0', condition, ${options(0, true, 'defaultSlot')})"`,
            );
            expect(extensionEntry.innerTemplate).toContain(
                `v-if="$swLegacyBlockElse('test_block:0', ${options(0, false, 'shimExtension')})"`,
            );
            expect(extensionEntry.legacyConditionCases).toEqual([
                {
                    chainKey: 'test_block:0',
                    caseCount: 1,
                    caseStartIndex: 0,
                },
            ]);
        });

        it('rewrites legacy Twig chains continuing across different block entries', () => {
            const [
                leadingEntry,
                fallbackEntry,
            ] = transformLegacyTwigBlockSequenceConditionals(
                [
                    {
                        blockName: 'test_block',
                        innerTemplate: '<div v-if="condition">Test</div>',
                    },
                    {
                        blockName: 'test_block2',
                        innerTemplate: '<div v-else>Test else</div>',
                    },
                ],
                TEST_COMPONENT,
            );

            expect(leadingEntry.innerTemplate).toContain(
                `v-if="$swLegacyBlockIf('test_block:0', condition, ${options(0, true, 'shimExtension')})"`,
            );
            expect(fallbackEntry.innerTemplate).toContain(
                `v-if="$swLegacyBlockElse('test_block:0', ${options(1, false, 'shimExtension')})"`,
            );
            expect(fallbackEntry.legacyConditionCases).toEqual([
                {
                    chainKey: 'test_block:0',
                    caseCount: 1,
                    caseStartIndex: 1,
                },
            ]);
        });

        it('rewrites condition chains across more than two chained native extensions', () => {
            const transformedTemplate = transformLegacyBlockConditionals(`
                <div>
                    <sw-block name="test_block">
                        <div v-if="condition">Test</div>
                    </sw-block>

                    <sw-block name="test_block_two">
                        <sw-block extends="test_block">
                            <sw-block-parent />
                            <div v-else-if="condition2">Test 2</div>
                        </sw-block>
                    </sw-block>

                    <sw-block name="test_block_three">
                        <sw-block extends="test_block_two">
                            <sw-block-parent />
                            <div v-else-if="condition3">Test 3</div>
                        </sw-block>
                    </sw-block>
                </div>
            `);

            expect(transformedTemplate).toContain(
                `v-if="$swLegacyBlockIf('test_block:0', condition, ${options(0, true, 'defaultSlot')})"`,
            );
            expect(transformedTemplate).toContain(
                `v-if="$swLegacyBlockElseIf('test_block:0', condition2, ${options(0, false, 'nativeExtension')})"`,
            );
            expect(transformedTemplate).toContain(
                `v-if="$swLegacyBlockElseIf('test_block:0', condition3, ${options(1, false, 'nativeExtension')})"`,
            );
        });

        it('rewrites a trailing chain while keeping a restarted chain separate', () => {
            const transformedTemplate = transformLegacyBlockConditionals(`
                <div>
                    <sw-block name="test-block">
                        <div v-if="showPrimary" class="primary-branch">primary</div>
                    </sw-block>

                    <sw-block extends="test-block">
                        <sw-block-parent />
                        <div v-else-if="showSecondary" class="secondary-branch">secondary</div>

                        <div class="chain-cut">cut</div>

                        <div v-if="showRestart" class="restart-branch">restart</div>
                        <div v-else-if="showRestartAlternative" class="restart-alternative-branch">alternative</div>
                    </sw-block>

                    <sw-block extends="test-block">
                        <sw-block-parent />
                        <div v-else class="restart-fallback-branch">fallback</div>
                    </sw-block>
                </div>
            `);

            expect(transformedTemplate).toContain(
                `v-if="$swLegacyBlockIf('test-block:0', showPrimary, ${options(0, true, 'defaultSlot')})"`,
            );
            expect(transformedTemplate).toContain(
                `v-if="$swLegacyBlockElseIf('test-block:0', showSecondary, ${options(0, false, 'nativeExtension')})"`,
            );
            expect(transformedTemplate).toContain(
                `v-if="$swLegacyBlockIf('test-block:1', showRestart, ${options(0, true, 'nativeExtension')})"`,
            );
            expect(transformedTemplate).toContain(
                `v-if="$swLegacyBlockElseIf('test-block:1', showRestartAlternative, ${options(1, false, 'nativeExtension')})"`,
            );
            expect(transformedTemplate).toContain(
                `v-if="$swLegacyBlockElse('test-block:1', ${options(2, false, 'nativeExtension')})"`,
            );
        });
    });
});

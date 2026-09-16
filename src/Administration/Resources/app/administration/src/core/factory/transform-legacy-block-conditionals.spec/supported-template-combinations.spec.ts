/**
 * @sw-package framework
 */
import transformLegacyBlockConditionals, {
    transformLegacyTwigBlockSequenceConditionals,
} from '../transform-legacy-block-conditionals';
import { options, TEST_COMPONENT } from './fixtures';

describe('core/factory/transform-legacy-block-conditionals.ts - supported template combinations', () => {
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

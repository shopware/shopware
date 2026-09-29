/**
 * @sw-package framework
 */
import { transformLegacyTwigBlockSequenceConditionals } from '../transform-legacy-block-conditionals';
import { expectTemplateCompiles, options, TEST_COMPONENT } from './fixtures';

describe('core/factory/transform-legacy-block-conditionals.ts - Twig shim sequence rewrite', () => {
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
        expectTemplateCompiles(entry.innerTemplate);
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
        expectTemplateCompiles(entry.innerTemplate);
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
        expectTemplateCompiles(entry.innerTemplate);
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
        expectTemplateCompiles(entry.innerTemplate);
    });
});

/**
 * @sw-package framework
 */

import { MULTI_ROOT } from './assert-single-root';
import { OPTION_HANDLERS } from './option-handlers';
import { convertFixture, convertSource, fixtureNames } from './spec-helpers';
import { LIFECYCLE_HOOKS, OPTION_TIERS } from './tables';
import { TWIG_PARENT_BLOCKER, transformTemplate } from './transform-template';

describe('scripts/codemods/sfc-migration', () => {
    describe('fixture snapshots (outcome, reasons and generated SFC per fixture)', () => {
        it.each(fixtureNames())('converts %s', async (name) => {
            const result = await convertFixture(name);

            expect(result).toMatchSnapshot();
        });
    });

    // classifyOptions() reads OPTION_TIERS before dispatching, so an option in both tables would
    // never reach its handler.
    it('assigns each option either a tier or a handler, never both', () => {
        const dispatched = [...Object.keys(OPTION_HANDLERS), ...Object.keys(LIFECYCLE_HOOKS)];

        expect(dispatched.filter((option) => option in OPTION_TIERS)).toEqual([]);
    });

    describe('outcome expectations (guard the snapshots against silent regressions)', () => {
        it('keeps array injection conservative until its ref-unwrapping contract is proven', async () => {
            const result = await convertFixture('sw-simple-card');

            expect(result.outcome).toBe('partial');
            expect(result.reasons).toContain('array inject declaration requires runtime ref-unwrapping verification');
            expect(result.sfc).toContain('swDefinePublic({');
            expect(result.sfc).not.toContain('this.');
            expect(result.sfc).not.toContain('$dataScope');
        });

        it('marks TODO-tier features as partial but still emits a valid draft', async () => {
            const result = await convertFixture('sw-partial-todos');

            expect(result.outcome).toBe('partial');
            expect(result.reasons).toEqual(
                expect.arrayContaining([
                    expect.stringContaining('inject'),
                    expect.stringContaining('metaInfo'),
                    expect.stringContaining('shortcuts'),
                    expect.stringContaining('$device'),
                ]),
            );
            expect(result.sfc).toContain('TODO(sfc-migration)');
        });

        it('leaves a this.<member> shadowed by a local binding unrewritten', async () => {
            const result = await convertFixture('sw-shadowed-locals');

            expect(result.outcome).toBe('partial');
            expect(result.reasons).toEqual(
                expect.arrayContaining([
                    'this.currentPage is shadowed by a local binding',
                    'this.perPage is shadowed by a local binding',
                    'this.iconSvgData is shadowed by a local binding',
                    'this.$route is shadowed by a local binding',
                    "template ref 'modalContent' is shadowed by a local binding",
                ]),
            );

            // The shadowed references keep their original text instead of resolving to the local.
            expect(result.sfc).toContain('this.perPage = Number(perPage)');
            expect(result.sfc).not.toContain('perPage.value = Number(perPage)');
            expect(result.sfc).toContain('const currentPage = this.currentPage');
            expect(result.sfc).not.toContain('const currentPage = currentPage.value');

            // A shadowed template ref must not be declared either — nothing would ever assign it.
            expect(result.sfc).not.toContain('const modalContent = ref(null)');

            // A binding in a sibling nested function does not shadow, and a local named after a
            // prop cannot shadow `props.<name>`.
            expect(result.sfc).toContain('items.value');
            expect(result.sfc).toContain('props.title');
        });

        it('moves module-level code into a sibling module the SFC imports what it reads from', async () => {
            const result = await convertFixture('sw-module-level-code');

            expect(result).toMatchObject({ outcome: 'full', reasons: [] });
            expect(result.sfc).not.toContain('<script data-sfc-migration-module');
            expect(result.sfc).toContain(
                "import { LABEL, sharedCache, buildCriteria, type Row } from './sw-module-level-code.module';",
            );
            expect(result.module?.fileName).toBe('sw-module-level-code.module.ts');
            expect(result.module?.source).toContain(
                "Shopware.Service('loginService').addOnLoginListener(() => sharedCache.clear());",
            );
            expect(result.module?.source).toContain('export { LABEL, sharedCache, buildCriteria, type Row };');
            // Only the conversion itself read `Component`, so its destructure is gone.
            expect(result.module?.source).not.toContain('const { Component } = Shopware;');
        });

        it('keeps authored imports in the SFC and moves a Shopware read it still uses into the sibling', async () => {
            const result = await convertFixture('sw-wrap-config');

            expect(result.sfc).toContain("import './sw-wrap-config.scss';");
            expect(result.sfc).toContain("import { Criteria } from './sw-wrap-config.module';");
            expect(result.module?.source).toContain('const { Criteria } = Shopware.Data;');
            expect(result.module?.source).not.toContain('Component');
        });

        it('writes no sibling module when only imports and unread Shopware reads surround the options', async () => {
            const jsSource = `
                import template from './sw-imports-only.html.twig';
                import { debounce } from 'lodash-es';

                /**
                 * @sw-package framework
                 */

                const { Component } = Shopware;

                export default Component.wrapComponentConfig({
                    template,
                    methods: {
                        later() {
                            return debounce(() => {}, 10);
                        },
                    },
                });
            `;
            const importsOnly = await convertSource('sw-imports-only', jsSource);

            expect(importsOnly).toMatchObject({ outcome: 'full', module: null });
            expect(importsOnly.sfc).toContain("import { debounce } from 'lodash-es';");
            expect(importsOnly.sfc).toContain(' * @sw-package framework');
            expect(importsOnly.sfc).not.toContain('Shopware');
        });

        it('imports a Vue helper once when the component already imports it, and drops its lint directive', async () => {
            const jsSource = `
                import template from './sw-vue-import.html.twig';
                import { computed } from 'vue';

                // eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
                export default {
                    template,
                    computed: {
                        doubled() {
                            return computed(() => 2).value;
                        },
                    },
                };
            `;
            const result = await convertSource('sw-vue-import', jsSource);

            expect(result).toMatchObject({ outcome: 'full', reasons: [], module: null });
            expect(result.sfc?.match(/import \{[^}]*\bcomputed\b[^}]*\} from 'vue';/g)).toEqual([
                "import { computed } from 'vue';",
            ]);
            expect(result.sfc).not.toContain('eslint-disable-next-line');
        });

        it.each([
            [
                'a module-level binding',
                'const format = (value) => `#${value}`;',
                'format',
                'format(value)',
            ],
            [
                'a global',
                '',
                'setTimeout',
                'setTimeout(() => {}, 0)',
            ],
        ])('refuses a member named like %s the component reads', async (_label, prelude, member, use) => {
            const jsSource = `
                import template from './sw-outer-shadow.html.twig';
                ${prelude}
                export default {
                    template,
                    methods: {
                        label(value) {
                            return ${use};
                        },
                        ${member}(value) {
                            return value;
                        },
                    },
                };
            `;
            const result = await convertSource('sw-outer-shadow', jsSource);

            expect(result).toMatchObject({
                outcome: 'skipped',
                reasons: [`binding '${member}' would shadow the module-level or global '${member}' the component reads`],
            });
        });

        it('refuses a module-level binding the component reassigns, which an import cannot be', async () => {
            const jsSource = `
                import template from './sw-module-counter.html.twig';
                let counter = 0;
                export default {
                    template,
                    methods: {
                        bump() {
                            counter += 1;
                            return counter;
                        },
                    },
                };
            `;
            const result = await convertSource('sw-module-counter', jsSource);

            expect(result).toMatchObject({
                outcome: 'skipped',
                reasons: [
                    "module-level 'counter' is reassigned by the component, but the sibling module exports it read-only",
                ],
            });
        });

        it('skips components using this.$super', async () => {
            const result = await convertFixture('sw-super-demo');

            expect(result.outcome).toBe('skipped');
            expect(result.reasons).toEqual(['this.$super']);
        });

        it('skips components whose name option differs from the directory name', async () => {
            const result = await convertFixture('sw-name-mismatch');

            expect(result.outcome).toBe('skipped');
            expect(result.reasons).toEqual(["name 'sw-totally-different' does not match the directory name"]);
        });

        // Two top-level blocks around the two halves of one chain: the chain is reconnected, but the
        // component now renders two blocks where it rendered one branch, hence the partial.
        it('reconnects a v-if/v-else chain that the block conversion split into siblings', async () => {
            const result = await convertFixture('sw-cross-velse');

            expect(result.outcome).toBe('partial');
            expect(result.reasons).toEqual([MULTI_ROOT]);
            expect(result.sfc).toContain(
                '<template v-if="active"><!-- Keeps the conditional chain connected across sw-block. --></template>',
            );
        });

        it('leaves a template that was multi-root before the conversion alone', async () => {
            const result = await convertFixture('sw-already-multi-root');

            expect(result.outcome).toBe('full');
            expect(result.reasons).toEqual([]);
        });

        it('refuses a binding named after a component tag the template renders', async () => {
            const result = await convertFixture('sw-tag-collision');

            expect(result.outcome).toBe('skipped');
            expect(result.reasons).toEqual(["binding 'routerLink' shadows a component tag the template renders"]);
        });

        // The template only sees what the SFC imports from the sibling module, which is what the setup
        // body reads — so an unread module binding named like a tag is harmless, a read one is refused.
        it.each([
            ['an unread', 'return 1;', { outcome: 'full', reasons: [] }],
            [
                'a read',
                'return swBlock;',
                {
                    outcome: 'skipped',
                    reasons: ["validation: binding 'swBlock' shadows a component tag the template renders"],
                },
            ],
        ])('handles an %s module binding named after the emitted sw-block', async (_label, body, expected) => {
            const jsSource = `
                import template from './sw-module-collision.html.twig';
                const swBlock = false;
                export default { name: 'sw-module-collision', template, methods: { read() { ${body} } } };
            `;
            const result = await convertSource('sw-module-collision', jsSource);

            expect(result).toMatchObject(expected);
        });

        // A ref is nearly always named after the component it points at, and the `ref` attribute in
        // the template names it too, so it cannot be renamed around the collision either.
        it('refuses a template ref named after a component tag the template renders', async () => {
            const result = await convertFixture('sw-ref-tag-collision');

            expect(result.outcome).toBe('skipped');
            expect(result.reasons).toEqual([
                "template ref 'swSelectResultList' shadows a component tag the template renders",
            ]);
        });

        // A Twig comment renders nothing; keeping it outside <template> preserves the note without
        // turning it into a second root node in development.
        it('moves a root Twig comment outside the generated template', async () => {
            const rootComment = '<!-- @deprecated tag:v6.8.0 - Will be removed, use mt-thing instead -->';
            const result = await convertFixture('sw-root-comment');

            expect(result.outcome).toBe('full');
            expect(result.sfc?.startsWith(`${rootComment}\n<template>`)).toBe(true);
            expect(result.sfc).not.toContain(`<template>\n    ${rootComment}`);
        });

        it.each(['<div>content</div>', '<some-component />'])(
            'keeps a non-block root single-rooted when preceded by a Twig comment: %s',
            (root) => {
                const result = transformTemplate(`{# note #}\n${root}`);

                expect(result.template?.trim()).toBe(root);
                expect(result.sfcComments).toEqual(['<!-- note -->']);
                expect(result.warnings).toEqual([]);
            },
        );

        // Every authoring form has to be refused: the leftover-twig check only looks for `{%`/`{#`,
        // so a surviving `{{ parent() }}` would compile as a live interpolation and fail at runtime.
        it.each([
            '{% block a_b %}{% parent %}{% endblock %}',
            '{% block a_b %}{{ parent() }}{% endblock %}',
            '{% block a_b %}{%- parent -%}{% endblock %}',
        ])('refuses %s, which only base output cannot express', (twig) => {
            expect(transformTemplate(twig)).toEqual({ template: null, blockers: [TWIG_PARENT_BLOCKER] });
        });

        // A `-->` in the body would close the generated comment early and spill the rest into
        // rendered markup — output Vue parses happily, so nothing downstream would catch it.
        it.each([
            ['{# see --> here #}', '<!-- see -- > here -->'],
            ['{# see --!> here #}', '<!-- see -- !> here -->'],
            ['{# arrow ---> tail #}', '<!-- arrow --- > tail -->'],
        ])('converts %s without letting the comment terminate early', (twig, expected) => {
            const result = transformTemplate(`<div>${twig}<span>kept</span></div>`);

            expect(result.blockers).toEqual([]);
            expect(result.template).toBe(`<div>${expected}<span>kept</span></div>`);
        });

        // The block keeps its name and its position around the slot content, so an override still
        // targets exactly what it targeted before the inversion.
        it('hoists a named slot out of the twig block that wrapped it', async () => {
            const result = await convertFixture('sw-block-named-slot');

            expect(result.outcome).toBe('full');
            expect(result.sfc).toContain('<template #modal-footer>');
            expect(result.sfc?.indexOf('<template #modal-footer>')).toBeLessThan(
                result.sfc!.indexOf('<sw-block name="sw_block_named_slot_footer">'),
            );
        });

        it('skips a component whose twig uses {% parent %}', async () => {
            const result = await convertFixture('sw-twig-parent');

            expect(result).toEqual({ outcome: 'skipped', reasons: [TWIG_PARENT_BLOCKER], sfc: null, module: null });
        });

        it('keeps a named slot that belongs to a child component inside the block', async () => {
            const result = await convertFixture('sw-slot-in-child');

            expect(result.outcome).toBe('full');
            expect(result.reasons).toEqual([]);
            expect(result.sfc).toContain('#modal-footer');
        });

        it('preserves function contracts and special JSDoc when rendering setup functions', async () => {
            const specialJsDoc =
                '@deprecated tag:v6.8.0 @experimental stableVersion:v6.9.0 feature:ADMIN_MIXIN_COMPOSABLES @internal @private';
            const jsSource = `
                    import template from './sw-function-contracts.html.twig';

                    export default {
                        template,
                        methods: {
                            /** ${specialJsDoc} */
                            typed<T>(value: T): T {
                                return value;
                            },
                        },
                    };
                `;
            const result = await convertSource('sw-function-contracts', jsSource, { lang: 'ts' });

            expect(result.outcome).toBe('full');
            expect(result.sfc).toContain(specialJsDoc);
            expect(result.sfc).toMatch(
                new RegExp(`const typed =\\s+/\\*\\* ${specialJsDoc} \\*/\\s+function <T>\\(value: T\\): T`),
            );
        });
    });
});

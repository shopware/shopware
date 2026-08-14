/**
 * @sw-package framework
 */

import { SKIP_RULES, TODO_RULES, buildReport, collectGroups, normalizeReason } from './analyze-skips';
import type { MigrationResult } from './run-sfc-migration';

describe('scripts/codemods/sfc-migration/analyze-skips', () => {
    describe('normalizeReason', () => {
        it.each([
            [
                'unsupported twig syntax: {% if config.enabled %}',
                'unsupported twig syntax: `{% if %}`',
                'template',
            ],
            [
                'unsupported twig syntax: {%- set foo = 1 -%}',
                'unsupported twig syntax: `{% set %}`',
                'template',
            ],
            [
                'orphaned cross-block v-else (no preceding v-if)',
                'orphaned cross-block v-else (no preceding v-if)',
                'template',
            ],
            [
                'named slot inside a twig block (<sw-block> renders only its default slot)',
                'named slot inside a twig block (`<sw-block>` renders only its default slot)',
                'template',
            ],
            [
                '{% parent %} needs override output (the codemod only writes base components)',
                '`{% parent %}` needs override output',
                'template',
            ],
            [
                "binding 'router' collides with a generated helper",
                'binding collides with a generated helper',
                'script',
            ],
            [
                "name 'sw-other-name' does not match the directory name",
                'component name does not match the directory name',
                'script',
            ],
            [
                'mixins',
                'option `mixins`',
                'script',
            ],
            [
                'this.$super',
                '`this.$super` usage',
                'script',
            ],
            [
                'validation: Element is missing end tag.',
                'validation: Element is missing end tag.',
                'validation',
            ],
            [
                'script parse error: Unexpected token (12:5)',
                'script parse error: Unexpected token',
                'script',
            ],
            [
                'prettier: Unexpected closing tag "div". (71:5)\n  69 |    </div>\n> 71 |    </div>',
                'prettier: Unexpected closing tag "div".',
                'validation',
            ],
            [
                'no template import (render function or inherited template)',
                'no template import (render function or inherited template)',
                'precheck',
            ],
        ])('groups skip reason %s as %s', (raw, label, stage) => {
            expect(normalizeReason(raw, SKIP_RULES)).toEqual({ label, stage });
        });

        it.each([
            [
                "convert 'provide' manually",
                'option `provide` needs manual conversion',
                'option',
            ],
            [
                "unknown option 'apollo'",
                'unknown option `apollo`',
                'option',
            ],
            [
                'unmapped this.$store',
                'unmapped `this.$store`',
                'this-rewrite',
            ],
            [
                'unmapped this.someHelper',
                'unmapped `this.<member>` (no matching binding)',
                'this-rewrite',
            ],
            [
                'this.perPage is shadowed by a local binding',
                '`this.<member>` shadowed by a local binding',
                'this-rewrite',
            ],
            [
                'this.$route is shadowed by a local binding',
                '`this.<member>` shadowed by a local binding',
                'this-rewrite',
            ],
            [
                "template ref 'modalContent' is shadowed by a local binding",
                'template ref shadowed by a local binding',
                'this-rewrite',
            ],
            [
                'module-level code outside the default export runs once per component instance',
                'module-level code outside the default export',
                'module',
            ],
            [
                'named export outside the default export is dropped by the index.js shim',
                'named export outside the default export',
                'module',
            ],
            [
                '`this.repository` inside a nested function keeps its own `this`',
                '`this` inside a nested function (own `this`)',
                'this-rewrite',
            ],
            [
                "unsupported watch entry 'entity.name'",
                'unsupported watch entry',
                'option',
            ],
            [
                "unsupported computed entry 'salesChannel'",
                'unsupported computed entry',
                'option',
            ],
            [
                'spread in computed',
                'spread in computed',
                'option',
            ],
            [
                'mounted is not a plain function',
                'lifecycle/created hook is not a plain function',
                'option',
            ],
        ])('groups TODO reason %s as %s', (raw, label, stage) => {
            expect(normalizeReason(raw, TODO_RULES)).toEqual({ label, stage });
        });

        it('keeps unmatched reasons verbatim under the other stage', () => {
            expect(normalizeReason('something brand new', SKIP_RULES)).toEqual({
                label: 'something brand new',
                stage: 'other',
            });
        });
    });

    describe('collectGroups', () => {
        const reports: MigrationResult['reports'] = [
            {
                name: 'sw-a',
                dir: '/a',
                outcome: 'skipped',
                registration: 'register',
                reasons: [
                    'mixins',
                    'this.$super',
                ],
            },
            { name: 'sw-b', dir: '/b', outcome: 'skipped', registration: 'extend', reasons: ['mixins'] },
            {
                name: 'sw-c',
                dir: '/c',
                outcome: 'partial',
                registration: 'register',
                reasons: [
                    'unmapped this.$store',
                    'unmapped this.$store',
                ],
            },
            { name: 'sw-d', dir: '/d', outcome: 'full', registration: 'unregistered', reasons: [] },
        ];

        it('counts distinct components per normalized skip reason, sorted by frequency', () => {
            const groups = collectGroups(reports, 'skipped', SKIP_RULES);

            expect(
                groups.map((group) => [
                    group.label,
                    group.components.size,
                ]),
            ).toEqual([
                [
                    'option `mixins`',
                    2,
                ],
                [
                    '`this.$super` usage',
                    1,
                ],
            ]);
        });

        it('counts same-named components in different directories separately', () => {
            const groups = collectGroups(
                [
                    { name: 'sw-a', dir: '/x/sw-a', outcome: 'skipped', registration: 'register', reasons: ['mixins'] },
                    { name: 'sw-a', dir: '/y/sw-a', outcome: 'skipped', registration: 'register', reasons: ['mixins'] },
                ],
                'skipped',
                SKIP_RULES,
            );

            expect(groups[0].components.size).toBe(2);
            expect(groups[0].classCounts.get('register')).toBe(2);
        });

        it('splits the component count by registration class', () => {
            const groups = collectGroups(reports, 'skipped', SKIP_RULES);

            expect([...groups[0].classCounts]).toEqual([
                [
                    'register',
                    1,
                ],
                [
                    'extend',
                    1,
                ],
            ]);
        });

        it('counts a component once per class even when it hits a reason twice', () => {
            const groups = collectGroups(
                [
                    {
                        name: 'sw-a',
                        dir: '/a',
                        outcome: 'skipped',
                        registration: 'extend',
                        reasons: [
                            'mixins',
                            'mixins',
                        ],
                    },
                ],
                'skipped',
                SKIP_RULES,
            );

            expect(groups[0].classCounts.get('extend')).toBe(1);
            expect(groups[0].occurrences).toBe(2);
        });

        it('counts every occurrence for partial TODOs but each component once', () => {
            const groups = collectGroups(reports, 'partial', TODO_RULES);

            expect(groups).toHaveLength(1);
            expect(groups[0].components.size).toBe(1);
            expect(groups[0].occurrences).toBe(2);
        });
    });

    describe('buildReport', () => {
        const result: MigrationResult = {
            stats: { full: 1, partial: 1, skipped: 2, alreadyMigrated: 0, error: 1 },
            inlineOverrides: [
                { file: '/x/index.js', name: 'sw-overridden' },
                { file: '/y/index.js', name: 'sw-overridden-too' },
            ],
            reports: [
                {
                    name: 'sw-a',
                    dir: '/a',
                    outcome: 'skipped',
                    registration: 'register',
                    reasons: ['mixins'],
                },
                {
                    name: 'sw-b',
                    dir: '/b',
                    outcome: 'skipped',
                    registration: 'extend',
                    reasons: [
                        'mixins',
                        'this.$parent',
                    ],
                },
                {
                    name: 'sw-c',
                    dir: '/c',
                    outcome: 'partial',
                    registration: 'register',
                    reasons: ["unknown option 'apollo'"],
                },
                { name: 'sw-d', dir: '/d', outcome: 'full', registration: 'register', reasons: [] },
                {
                    name: 'sw-e',
                    dir: '/e',
                    outcome: 'error',
                    registration: 'unregistered',
                    reasons: ['boom | with pipe'],
                },
            ],
        };

        it('renders summary, skip, TODO and error sections as markdown', () => {
            const report = buildReport(result, 'src/module', '2026-08-14');

            expect(report).toContain('| **total** | **5** |');
            expect(report).toContain('## Skip reasons (2 components)');
            expect(report).toContain('| option `mixins` | script | 2 | 1 | 1 | 0 | `sw-a`, `sw-b` |');
            expect(report).toContain('| `this.$parent` usage | script | 1 | 0 | 1 | 0 | `sw-b` |');
            expect(report).toContain('| unknown option `apollo` | option | 1 | 1 | 0 | 0 | 1 | `sw-c` |');
            expect(report).toContain('| `sw-e` | boom \\| with pipe |');
        });

        it('renders the class columns in a fixed order, without the unused override class', () => {
            const report = buildReport(result, 'src/module', '2026-08-14');

            expect(report).toContain('| Reason | Stage | Components | register | extend | unregistered | Examples |');
            expect(report).toContain(
                '| Reason | Stage | Components | register | extend | unregistered | Occurrences | Examples |',
            );
        });

        it('adds the override column once a component is registered through an override', () => {
            const report = buildReport(
                {
                    ...result,
                    reports: [
                        ...result.reports,
                        { name: 'sw-f', dir: '/f', outcome: 'skipped', registration: 'override', reasons: ['mixins'] },
                    ],
                },
                'src/module',
                '2026-08-14',
            );

            expect(report).toContain(
                '| Reason | Stage | Components | register | extend | override | unregistered | Examples |',
            );
            expect(report).toContain('| option `mixins` | script | 3 | 1 | 1 | 1 | 0 | `sw-a`, `sw-b`, `sw-f` |');
            expect(report).toContain('| override | 1 |');
        });

        it('summarizes the registration classes and the inline overrides', () => {
            const report = buildReport(result, 'src/module', '2026-08-14');

            expect(report).toContain(
                [
                    '| Class | Components |',
                    '|---|---:|',
                    '| register | 3 |',
                    '| extend | 1 |',
                    '| unregistered | 1 |',
                ].join('\n'),
            );
            expect(report).toContain(
                'Additionally, 2 inline `Component.override(...)` configs were found — invisible to the codemod, reported only.',
            );
        });

        it('omits the inline override line when there is none', () => {
            const report = buildReport({ ...result, inlineOverrides: [] }, 'src/module', '2026-08-14');

            expect(report).not.toContain('inline `Component.override(...)`');
        });
    });
});

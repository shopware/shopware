/**
 * @sw-package framework
 */

import reconstructInnerTemplate from 'src/core/factory/reconstruct-twig-template';

type TestToken = {
    type: 'raw' | 'logic';
    value?: string;
    token?: { type?: string; blockName?: string; output?: TestToken[] };
};

/** Minimal token shapes that match what TwigJS produces for raw HTML segments. */
function rawToken(value: string): TestToken {
    return { type: 'raw', value };
}

/** A token for the custom `{% parent %}` tag (type: 'parent'). */
function parentToken(): TestToken {
    return { type: 'logic', token: { type: 'parent' } };
}

/** A `{% block name %}` logic token whose body is the nested `output` array (TwigJS shape). */
function blockToken(blockName: string, output: TestToken[]): TestToken {
    return { type: 'logic', token: { blockName, output } };
}

/** An unknown logic token (e.g. {% if %}, {% for %}). */
function unknownLogicToken(): TestToken {
    return { type: 'logic', token: { type: 'Twig.logic.type.if' } };
}

describe('core/factory/reconstruct-twig-template.ts', () => {
    describe('reconstructInnerTemplate', () => {
        it('returns an empty string for an empty token array', () => {
            expect(reconstructInnerTemplate([])).toBe('');
        });

        it('passes raw HTML tokens through verbatim', () => {
            const tokens = [rawToken('<div class="foo"></div>')];

            expect(reconstructInnerTemplate(tokens)).toBe('<div class="foo"></div>');
        });

        it('concatenates multiple raw tokens in order', () => {
            const tokens = [
                rawToken('<div>'),
                rawToken('<span>'),
                rawToken('</span></div>'),
            ];

            expect(reconstructInnerTemplate(tokens)).toBe('<div><span></span></div>');
        });

        it('replaces a parent token with the <sw-block-parent /> placeholder', () => {
            const tokens = [parentToken()];

            expect(reconstructInnerTemplate(tokens)).toBe('<sw-block-parent />');
        });

        it('correctly places <sw-block-parent /> between surrounding raw HTML', () => {
            const tokens = [
                rawToken('<div class="before">'),
                parentToken(),
                rawToken('</div>'),
            ];

            expect(reconstructInnerTemplate(tokens)).toBe('<div class="before"><sw-block-parent /></div>');
        });

        it('reconstructs the inner template of a single {% block %} token (blockName + output)', () => {
            const tokens = [
                blockToken('inner_block', [rawToken('<div class="inner"></div>')]),
            ];

            expect(reconstructInnerTemplate(tokens)).toBe('<div class="inner"></div>');
        });

        it('recursively reconstructs two layers of {% block %} tokens (outer block contains inner block)', () => {
            const tokens = [
                blockToken('outer', [
                    rawToken('<div class="outer-open">'),
                    blockToken('inner', [rawToken('<span class="deep"></span>')]),
                    rawToken('</div>'),
                ]),
            ];

            expect(reconstructInnerTemplate(tokens)).toBe(
                '<div class="outer-open"><span class="deep"></span></div>',
            );
        });

        /**
         * Regression for `TwigToken`: shapes mirror observed TwigJS runtime output and
         * should be re-checked when upgrading the `twig` package (see JSDoc on the type).
         */
        it('matches composite TwigJS-like token trees (raw, blockName/output, parent, Twig.logic.type.*)', () => {
            const tokens = [
                rawToken('<p>'),
                blockToken('outer', [
                    rawToken('A'),
                    blockToken('inner', [rawToken('B'), parentToken()]),
                    rawToken('C'),
                ]),
                rawToken('</p>'),
                unknownLogicToken(),
            ];

            expect(reconstructInnerTemplate(tokens)).toBe('<p>AB<sw-block-parent />C</p>');
        });

        it('recursively handles a block body that contains a parent token', () => {
            const tokens = [
                blockToken('nested_with_parent', [
                    parentToken(),
                    rawToken('<div class="extra"></div>'),
                ]),
            ];

            expect(reconstructInnerTemplate(tokens)).toBe('<sw-block-parent /><div class="extra"></div>');
        });

        it('collapses unknown Twig logic tokens (if, for, …) to an empty string', () => {
            const tokens = [unknownLogicToken()];

            expect(reconstructInnerTemplate(tokens)).toBe('');
        });

        it('preserves raw tokens before and after an unknown logic token', () => {
            const tokens = [
                rawToken('<div class="before">'),
                unknownLogicToken(),
                rawToken('</div>'),
            ];

            expect(reconstructInnerTemplate(tokens)).toBe('<div class="before"></div>');
        });

        it('handles a raw token with an undefined value by treating it as an empty string', () => {
            const tokens = [{ type: 'raw' as const }];

            expect(reconstructInnerTemplate(tokens)).toBe('');
        });

        it('preserves Vue template syntax ({{ }}, v-if, :class) inside raw tokens verbatim', () => {
            const template = '<div :class="{ active: isActive }" v-if="show">{{ label }}</div>';
            const tokens = [rawToken(template)];

            expect(reconstructInnerTemplate(tokens)).toBe(template);
        });
    });
});

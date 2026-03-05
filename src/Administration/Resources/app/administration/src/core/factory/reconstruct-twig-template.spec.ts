/**
 * @sw-package framework
 */

import { reconstructInnerTemplate, containsParentToken } from 'src/core/factory/reconstruct-twig-template';

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

/** A nested `{% block name %}` token whose body is given by an output array. */
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

        it('recursively reconstructs the content of a nested {% block %} token', () => {
            const tokens = [
                blockToken('inner_block', [rawToken('<div class="inner"></div>')]),
            ];

            expect(reconstructInnerTemplate(tokens)).toBe('<div class="inner"></div>');
        });

        it('recursively handles a nested block that itself contains a parent token', () => {
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

    describe('containsParentToken', () => {
        it('returns false for an empty token array', () => {
            expect(containsParentToken([])).toBe(false);
        });

        it('returns false when the array contains only raw tokens', () => {
            expect(containsParentToken([rawToken('<div></div>')])).toBe(false);
        });

        it('returns false when the array contains only unknown logic tokens', () => {
            expect(containsParentToken([unknownLogicToken()])).toBe(false);
        });

        it('returns true when the array contains a parent token at the top level', () => {
            expect(containsParentToken([parentToken()])).toBe(true);
        });

        it('returns true when a parent token is mixed with raw tokens', () => {
            const tokens = [
                rawToken('<div>'),
                parentToken(),
                rawToken('</div>'),
            ];

            expect(containsParentToken(tokens)).toBe(true);
        });

        it('returns true when a parent token is nested inside a block token', () => {
            const tokens = [blockToken('nested_block', [parentToken()])];

            expect(containsParentToken(tokens)).toBe(true);
        });

        it('returns false when a nested block contains no parent token', () => {
            const tokens = [blockToken('nested_no_parent', [rawToken('<div></div>')])];

            expect(containsParentToken(tokens)).toBe(false);
        });

        it('returns true when a parent token is deeply nested two levels down', () => {
            const tokens = [
                blockToken('outer', [
                    blockToken('inner', [parentToken()]),
                ]),
            ];

            expect(containsParentToken(tokens)).toBe(true);
        });
    });
});

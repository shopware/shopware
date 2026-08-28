/**
 * @sw-package framework
 */

import Twig from 'twig';
import reconstructInnerTemplate, { type TwigToken } from 'src/core/factory/reconstruct-twig-template';

/**
 * Side-effect import: configures the TwigJS singleton (disables cache, registers
 * the {% parent %} tag, removes output-whitespace token definitions). Required so
 * the integration tests below use the same TwigJS state as production code.
 */
import 'src/core/factory/template.factory';

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
    describe('unit: reconstructInnerTemplate with manual token shapes', () => {
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

        it('wraps a nested {% block %} token in <sw-block> preserving the block name', () => {
            const tokens = [
                blockToken('outer_block', [
                    blockToken('inner_block', [rawToken('<div class="inner"></div>')]),
                ]),
            ];

            expect(reconstructInnerTemplate(tokens)).toBe(
                '<sw-block name="outer_block"><sw-block name="inner_block"><div class="inner"></div></sw-block></sw-block>',
            );
        });

        it('wraps a nested block in <sw-block> when it contains a parent token', () => {
            const tokens = [
                blockToken('nested_with_parent', [
                    parentToken(),
                    rawToken('<div class="extra"></div>'),
                ]),
            ];

            expect(reconstructInnerTemplate(tokens)).toBe(
                '<sw-block name="nested_with_parent"><sw-block-parent /><div class="extra"></div></sw-block>',
            );
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

describe('integration: reconstructInnerTemplate with real TwigJS parser output', () => {
    it('returns an empty string for an empty twig template', () => {
        const compiled = Twig.twig({ data: '', rethrow: true });

        expect(reconstructInnerTemplate(compiled.tokens as TwigToken[])).toBe('');
    });

    it('passes raw HTML through verbatim', () => {
        const compiled = Twig.twig({ data: '<div class="foo"></div>', rethrow: true });

        expect(reconstructInnerTemplate(compiled.tokens as TwigToken[])).toBe('<div class="foo"></div>');
    });

    it('replaces {% parent %} with <sw-block-parent /> before surrounding HTML', () => {
        const compiled = Twig.twig({ data: '{% parent %}<div class="after"></div>', rethrow: true });

        expect(reconstructInnerTemplate(compiled.tokens as TwigToken[])).toBe(
            '<sw-block-parent /><div class="after"></div>',
        );
    });

    it('replaces {% parent %} with <sw-block-parent /> after surrounding HTML', () => {
        const compiled = Twig.twig({ data: '<div class="before"></div>{% parent %}', rethrow: true });

        expect(reconstructInnerTemplate(compiled.tokens as TwigToken[])).toBe(
            '<div class="before"></div><sw-block-parent />',
        );
    });

    it('wraps a nested {% block %} in <sw-block> and replaces {% parent %}', () => {
        const compiled = Twig.twig({
            data: '{% block inner %}{% parent %}<div class="extra"></div>{% endblock %}',
            rethrow: true,
        });

        expect(reconstructInnerTemplate(compiled.tokens as TwigToken[])).toBe(
            '<sw-block name="inner"><sw-block-parent /><div class="extra"></div></sw-block>',
        );
    });
});

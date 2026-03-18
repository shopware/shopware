import path from 'path';
import fs from 'fs';
import { replaceBlocks, wrapInTemplate } from './transform-template';

const fixturesDir = path.join(__dirname, '__fixtures__');

describe('scripts/codemods/sfc-migration/transform-template', () => {
    describe('replaceBlocks', () => {
        it('converts a twig block start tag to an sw-block opening tag', () => {
            const input = '{% block sw_my_card %}\n<div>content</div>\n{% endblock %}';
            const result = replaceBlocks(input);

            expect(result).toContain('<sw-block name="sw_my_card" :data="$dataScope">');
        });

        it('converts a twig block end tag to an sw-block closing tag', () => {
            const input = '{% block sw_foo %}\n<div/>\n{% endblock %}';
            const result = replaceBlocks(input);

            expect(result).toContain('</sw-block>');
            expect(result).not.toContain('{% endblock %}');
        });

        it('converts {{ parent() }} to <sw-block-parent/>', () => {
            const input = '{% block sw_foo %}\n{{ parent() }}\n{% endblock %}';
            const result = replaceBlocks(input);

            expect(result).toContain('<sw-block-parent/>');
            expect(result).not.toContain('{{ parent() }}');
        });

        it('converts {% parent() %} syntax to <sw-block-parent/>', () => {
            const input = '{% block sw_foo %}\n{% parent() %}\n{% endblock %}';
            const result = replaceBlocks(input);

            expect(result).toContain('<sw-block-parent/>');
        });

        it('strips {% extends %} lines entirely', () => {
            const input = "{% extends '@Storefront/storefront/page/content/index.html.twig' %}\n{% block sw_foo %}\n<div/>\n{% endblock %}";
            const result = replaceBlocks(input);

            expect(result).not.toContain('{% extends');
            expect(result).not.toContain('@Storefront/storefront');
        });

        it('strips eslint-disable-next-line comments for no-twigjs-blocks', () => {
            const input =
                '{% block sw_foo %}\n<!-- eslint-disable-next-line sw-deprecation-rules/no-twigjs-blocks -->\n{{ parent() }}\n{% endblock %}';
            const result = replaceBlocks(input);

            expect(result).not.toContain('<!-- eslint-disable-next-line sw-deprecation-rules/no-twigjs-blocks -->');
        });

        it('strips inline eslint-disable comments from a line while preserving the rest of that line', () => {
            const input =
                '{% block sw_foo %}\n<div><!-- eslint-disable-next-line sw-deprecation-rules/no-twigjs-blocks -->text</div>\n{% endblock %}';
            const result = replaceBlocks(input);

            expect(result).not.toContain('eslint-disable-next-line sw-deprecation-rules/no-twigjs-blocks');
            expect(result).toContain('<div>text</div>');
        });

        it('returns null when the input contains no twig blocks', () => {
            const input = '<div class="plain-html">\n    <span>No twig here</span>\n</div>';
            const result = replaceBlocks(input);

            expect(result).toBeNull();
        });

        it('converts multiple sibling blocks in the same template', () => {
            const input =
                '{% block sw_header %}\n<header/>\n{% endblock %}\n{% block sw_content %}\n<main/>\n{% endblock %}';
            const result = replaceBlocks(input);

            expect(result).toContain('<sw-block name="sw_header" :data="$dataScope">');
            expect(result).toContain('<sw-block name="sw_content" :data="$dataScope">');
            expect((result?.match(/<\/sw-block>/g) ?? []).length).toBe(2);
        });

        it('preserves nested block structure', () => {
            const input = [
                '{% block sw_outer %}',
                '<div>',
                '    {% block sw_inner %}',
                '    <span>inner</span>',
                '    {% endblock %}',
                '</div>',
                '{% endblock %}',
            ].join('\n');

            const result = replaceBlocks(input);

            expect(result).toContain('<sw-block name="sw_outer" :data="$dataScope">');
            expect(result).toContain('<sw-block name="sw_inner" :data="$dataScope">');
            expect((result?.match(/<\/sw-block>/g) ?? []).length).toBe(2);

            const outerIndex = result!.indexOf('sw_outer');
            const innerIndex = result!.indexOf('sw_inner');
            expect(innerIndex).toBeGreaterThan(outerIndex);
        });

        it('preserves plain HTML content outside blocks verbatim', () => {
            const input = '{% block sw_foo %}\n<div class="my-class" data-value="42">keep me</div>\n{% endblock %}';
            const result = replaceBlocks(input);

            expect(result).toContain('<div class="my-class" data-value="42">keep me</div>');
        });

        it('handles block names with underscores and numbers', () => {
            const input = '{% block sw_product_detail_v2_content %}\n<div/>\n{% endblock %}';
            const result = replaceBlocks(input);

            expect(result).toContain('name="sw_product_detail_v2_content"');
        });

        it('handles extra whitespace around block tags', () => {
            const input = '{%  block   sw_spaced  %}\n<div/>\n{%  endblock  %}';
            const result = replaceBlocks(input);

            expect(result).toContain('<sw-block name="sw_spaced" :data="$dataScope">');
            expect(result).toContain('</sw-block>');
        });
    });

    describe('wrapInTemplate', () => {
        it('wraps content in a <template> tag', () => {
            const content = '<div>hello</div>';
            const result = wrapInTemplate(content);

            expect(result).toBe('<template>\n<div>hello</div>\n</template>');
        });

        it('handles multi-line content', () => {
            const content = '<div>\n    <span>text</span>\n</div>';
            const result = wrapInTemplate(content);

            expect(result.startsWith('<template>')).toBe(true);
            expect(result.endsWith('</template>')).toBe(true);
            expect(result).toContain('<span>text</span>');
        });

        it('handles empty content by producing an empty template block', () => {
            const result = wrapInTemplate('');

            expect(result).toBe('<template>\n\n</template>');
        });
    });

    describe('integrative tests using fixture files', () => {
        it('transforms the block-component fixture template and wraps it in <template>', () => {
            const twigContent = fs.readFileSync(
                path.join(fixturesDir, 'block-component.html.twig'),
                'utf8',
            );

            const transformed = replaceBlocks(twigContent);

            expect(transformed).not.toBeNull();
            expect(transformed).toContain('<sw-block name="sw_block_card" :data="$dataScope">');
            expect(transformed).toContain('<sw-block name="sw_block_card_header" :data="$dataScope">');
            expect(transformed).toContain('<sw-block name="sw_block_card_content" :data="$dataScope">');
            expect(transformed).toContain('<sw-block name="sw_block_card_footer" :data="$dataScope">');
            expect(transformed).toContain('<sw-block-parent/>');
            expect(transformed).not.toContain('{% block');
            expect(transformed).not.toContain('{% endblock %}');
            expect(transformed).not.toContain('eslint-disable-next-line sw-deprecation-rules/no-twigjs-blocks');

            const wrapped = wrapInTemplate(transformed!);
            expect(wrapped.startsWith('<template>')).toBe(true);
            expect(wrapped.endsWith('</template>')).toBe(true);
        });

        it('returns null for the simple-component fixture which has no twig blocks', () => {
            const twigContent = fs.readFileSync(
                path.join(fixturesDir, 'simple-component.html.twig'),
                'utf8',
            );

            const result = replaceBlocks(twigContent);

            expect(result).toBeNull();
        });

        it('matches the block-component transformed template snapshot', () => {
            const twigContent = fs.readFileSync(
                path.join(fixturesDir, 'block-component.html.twig'),
                'utf8',
            );

            const transformed = replaceBlocks(twigContent);
            expect(wrapInTemplate(transformed!)).toMatchSnapshot();
        });

        it('wrapping a plain html template produces a valid <template> snapshot', () => {
            const twigContent = fs.readFileSync(
                path.join(fixturesDir, 'simple-component.html.twig'),
                'utf8',
            );

            expect(wrapInTemplate(twigContent)).toMatchSnapshot();
        });
    });
});

import path from 'path';
import fs from 'fs';
import { transformTemplate } from './transform-template';

const fixturesDir = path.join(__dirname, '__fixtures__');

function readFixture(name: string): string {
    return fs.readFileSync(path.join(fixturesDir, name), 'utf8');
}

/**
 * Integrative tests for transformTemplate().
 *
 * Each test provides a complete .html.twig file and asserts that the entire
 * resulting <template> block is correct — not just isolated conversions.
 */
describe('scripts/codemods/sfc-migration/transform-template', () => {
    describe('block-component: twig block syntax is fully replaced across the whole template', () => {
        let result: string;

        beforeAll(() => {
            result = transformTemplate(readFixture('block-component.html.twig')).template;
        });

        it('wraps the entire output in a <template> tag', () => {
            expect(result.trimStart().startsWith('<template>')).toBe(true);
            expect(result.trimEnd().endsWith('</template>')).toBe(true);
        });

        it('converts all four {% block %} start tags to <sw-block name="..." :data="$dataScope">', () => {
            expect(result).toContain('<sw-block name="sw_block_card" :data="$dataScope">');
            expect(result).toContain('<sw-block name="sw_block_card_header" :data="$dataScope">');
            expect(result).toContain('<sw-block name="sw_block_card_content" :data="$dataScope">');
            expect(result).toContain('<sw-block name="sw_block_card_footer" :data="$dataScope">');
        });

        it('converts all {% endblock %} tags to </sw-block> — one per block', () => {
            const count = (result.match(/<\/sw-block>/g) ?? []).length;
            expect(count).toBe(4);
        });

        it('converts {{ parent() }} to <sw-block-parent/> and removes the eslint-disable comment above it', () => {
            expect(result).toContain('<sw-block-parent/>');
            expect(result).not.toContain('{{ parent()');
            expect(result).not.toContain('eslint-disable-next-line sw-deprecation-rules/no-twigjs-blocks');
        });

        it('leaves no twig syntax in the output', () => {
            expect(result).not.toContain('{%');
            expect(result).not.toContain('%}');
        });

        it('preserves all original HTML elements and Vue template expressions', () => {
            expect(result).toContain('class="sw-block-card"');
            expect(result).toContain('class="sw-block-card__header"');
            expect(result).toContain('<h3>{{ title }}</h3>');
            expect(result).toContain('<p>{{ description }}</p>');
            expect(result).toContain('<button @click="onAction">Action</button>');
        });

        it('matches the complete transformed template snapshot', () => {
            expect(result).toMatchSnapshot();
        });
    });

    describe('simple-component: plain HTML without twig blocks is wrapped without modification', () => {
        let result: string;

        beforeAll(() => {
            result = transformTemplate(readFixture('simple-component.html.twig')).template;
        });

        it('wraps the output in a <template> tag', () => {
            expect(result.trimStart().startsWith('<template>')).toBe(true);
            expect(result.trimEnd().endsWith('</template>')).toBe(true);
        });

        it('preserves every HTML element from the original file unchanged', () => {
            expect(result).toContain('class="sw-simple-card"');
            expect(result).toContain('class="sw-simple-card__title"');
            expect(result).toContain('class="sw-simple-card__description"');
            expect(result).toContain('<button class="sw-simple-card__action" @click="onSave">Save</button>');
            expect(result).toContain('{{ title }}');
            expect(result).toContain('{{ description }}');
        });

        it('introduces no <sw-block> elements when there were no twig blocks', () => {
            expect(result).not.toContain('<sw-block');
            expect(result).not.toContain('</sw-block>');
        });

        it('matches the complete wrapped template snapshot', () => {
            expect(result).toMatchSnapshot();
        });
    });

    describe('twig-comments: converts {# ... #} Twig comments to HTML comments', () => {
        let result: string;

        beforeAll(() => {
            result = transformTemplate(readFixture('twig-comments.html.twig')).template;
        });

        it('wraps the output in a <template> tag', () => {
            expect(result.trimStart().startsWith('<template>')).toBe(true);
            expect(result.trimEnd().endsWith('</template>')).toBe(true);
        });

        it('converts a single-line Twig comment to an HTML comment', () => {
            expect(result).toContain('<!-- @deprecated tag:v6.8.0 - Use mt-button instead -->');
        });

        it('converts inline Twig comments to HTML comments', () => {
            expect(result).toContain('<!-- This is an inline comment -->');
        });

        it('converts Twig comments with special characters to HTML comments', () => {
            expect(result).toContain('<!-- Multi-word comment with special chars: & < > -->');
        });

        it('contains no remaining Twig comment delimiters {# or #}', () => {
            expect(result).not.toContain('{#');
            expect(result).not.toContain('#}');
        });

        it('still converts Twig blocks correctly alongside comments', () => {
            expect(result).toContain('<sw-block name="sw_demo" :data="$dataScope">');
            expect(result).toContain('</sw-block>');
        });

        it('matches the complete transformed template snapshot', () => {
            expect(result).toMatchSnapshot();
        });
    });

    describe('extends-template: {% extends %} and its adjacent eslint-disable comment are stripped while block syntax is still converted', () => {
        let result: string;

        beforeAll(() => {
            result = transformTemplate(readFixture('extends-template.html.twig')).template;
        });

        it('wraps the output in a <template> tag', () => {
            expect(result.trimStart().startsWith('<template>')).toBe(true);
            expect(result.trimEnd().endsWith('</template>')).toBe(true);
        });

        it('removes the {% extends %} line', () => {
            expect(result).not.toContain('{% extends');
            expect(result).not.toContain('@Administration/administration/src/module/sw-foo');
        });

        it('removes the adjacent eslint-disable-next-line comment', () => {
            expect(result).not.toContain('eslint-disable-next-line sw-deprecation-rules/no-twigjs-blocks');
        });

        it('still converts block syntax to <sw-block> elements', () => {
            expect(result).toContain('<sw-block name="sw_foo_list" :data="$dataScope">');
            expect(result).toContain('</sw-block>');
        });

        it('leaves no twig syntax in the output', () => {
            expect(result).not.toContain('{%');
            expect(result).not.toContain('%}');
        });

        it('matches the complete transformed template snapshot', () => {
            expect(result).toMatchSnapshot();
        });
    });

    describe('extends-without-blocks: {% extends %} is stripped without introducing sw-block wrappers', () => {
        let result: string;

        beforeAll(() => {
            result = transformTemplate(readFixture('extends-without-blocks.html.twig')).template;
        });

        it('removes the {% extends %} line and adjacent eslint-disable comment', () => {
            expect(result).not.toContain('{% extends');
            expect(result).not.toContain('eslint-disable-next-line sw-deprecation-rules/no-twigjs-blocks');
            expect(result).not.toContain('@Administration/administration/src/module/sw-foo');
        });

        it('preserves the plain template content without adding sw-block wrappers', () => {
            expect(result).toContain('<div class="sw-foo">{{ title }}</div>');
            expect(result).not.toContain('<sw-block');
            expect(result).not.toContain('</sw-block>');
        });

        it('matches the complete transformed template snapshot', () => {
            expect(result).toMatchSnapshot();
        });
    });

    it('removes obsolete twig eslint-disable comments adjacent to block migration lines', () => {
        const result = transformTemplate(`
<!-- eslint-disable-next-line sw-deprecation-rules/no-twigjs-blocks -->
{% block sw_example %}
    <div>content</div>
{% endblock %}
<!-- eslint-disable-next-line sw-deprecation-rules/no-twigjs-blocks -->
        `).template;

        expect(result).toContain('<sw-block name="sw_example" :data="$dataScope">');
        expect(result).toContain('</sw-block>');
        expect(result).not.toContain('eslint-disable-next-line sw-deprecation-rules/no-twigjs-blocks');
    });

    it('removes obsolete twig eslint-disable comments adjacent to parent() migration lines', () => {
        const result = transformTemplate(`
{% block sw_example %}
<!-- eslint-disable-next-line sw-deprecation-rules/no-twigjs-blocks -->
{{ parent() }}
{% endblock %}
        `).template;

        expect(result).toContain('<sw-block-parent/>');
        expect(result).not.toContain('{{ parent() }}');
        expect(result).not.toContain('eslint-disable-next-line sw-deprecation-rules/no-twigjs-blocks');
    });

    it('removes double-quoted twig extends lines too', () => {
        const result = transformTemplate(`
<!-- eslint-disable-next-line sw-deprecation-rules/no-twigjs-blocks -->
{% extends "@Administration/administration/src/module/sw-foo/page/sw-foo-index/sw-foo-index.html.twig" %}
<div class="sw-foo">{{ title }}</div>
        `).template;

        expect(result).not.toContain('{% extends');
        expect(result).not.toContain('@Administration/administration/src/module/sw-foo');
        expect(result).not.toContain('eslint-disable-next-line sw-deprecation-rules/no-twigjs-blocks');
        expect(result).toContain('<div class="sw-foo">{{ title }}</div>');
    });
});

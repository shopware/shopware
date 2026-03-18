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
            result = transformTemplate(readFixture('block-component.html.twig'));
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
            result = transformTemplate(readFixture('simple-component.html.twig'));
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
});

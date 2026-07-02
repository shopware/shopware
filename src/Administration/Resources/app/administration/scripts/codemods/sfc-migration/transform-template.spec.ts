import path from 'path';
import fs from 'fs';
import { compile } from '@vue/compiler-dom';
import { transformTemplate } from './transform-template';

const fixturesDir = path.join(__dirname, '__fixtures__');

function readFixture(name: string): string {
    return fs.readFileSync(path.join(fixturesDir, name), 'utf8');
}

function expectTemplateCompiles(template: string): void {
    const body = template.replace(/^<template>\n/, '').replace(/\n<\/template>$/, '');

    expect(() => {
        compile(body, {
            onError: (error) => {
                throw error;
            },
        });
    }).not.toThrow();
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

    it('throws when the template contains {% extends %} with block syntax', () => {
        expect(() => transformTemplate(readFixture('extends-template.html.twig'))).toThrow(
            'Twig extends is not supported by the SFC migration codemod.',
        );
    });

    it('throws when the template contains {% extends %} without block syntax', () => {
        expect(() => transformTemplate(readFixture('extends-without-blocks.html.twig'))).toThrow(
            'Twig extends is not supported by the SFC migration codemod.',
        );
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

    it('throws for double-quoted twig extends lines too', () => {
        expect(() =>
            transformTemplate(`
<!-- eslint-disable-next-line sw-deprecation-rules/no-twigjs-blocks -->
{% extends "@Administration/administration/src/module/sw-foo/page/sw-foo-index/sw-foo-index.html.twig" %}
<div class="sw-foo">{{ title }}</div>
        `),
        ).toThrow('Twig extends is not supported by the SFC migration codemod.');
    });

    it('throws for dynamic twig extends expressions too', () => {
        expect(() =>
            transformTemplate(`
{% extends parentTemplate %}
<div class="sw-foo">{{ title }}</div>
        `),
        ).toThrow('Twig extends is not supported by the SFC migration codemod.');
    });

    it('inserts a guard before v-else continuations across sibling sw-blocks', () => {
        const result = transformTemplate(`
{% block sw_first %}
    <div v-if="test">true</div>
{% endblock %}

{% block sw_second %}
    <div v-else>false</div>
{% endblock %}
        `).template;

        expect(result).toContain('<div v-if="test">true</div>');
        expect(result).toContain(
            '<template v-if="(test)"><!-- Keeps the conditional chain connected across sw-block. --></template>',
        );
        expect(result).toContain('<div v-else>false</div>');
        expectTemplateCompiles(result);
    });

    it('inserts guards before v-else-if and v-else continuations across multiple sibling sw-blocks', () => {
        const result = transformTemplate(`
{% block sw_first %}
    <div v-if="firstCondition">first</div>
{% endblock %}

{% block sw_second %}
    <div v-else-if="secondCondition">second</div>
{% endblock %}

{% block sw_third %}
    <div v-else>fallback</div>
{% endblock %}
        `).template;

        expect(result).toContain('<div v-if="firstCondition">first</div>');
        expect(result).toContain(
            '<template v-if="(firstCondition)"><!-- Keeps the conditional chain connected across sw-block. --></template>',
        );
        expect(result).toContain('<div v-else-if="secondCondition">second</div>');
        expect(result).toContain(
            '<template v-if="(firstCondition) || (secondCondition)"><!-- Keeps the conditional chain connected across sw-block. --></template>',
        );
        expect(result).toContain('<div v-else>fallback</div>');
        expectTemplateCompiles(result);
    });

    it('keeps the full leading continuation chain inside a following sw-block', () => {
        const result = transformTemplate(`
{% block sw_first %}
    <div v-if="firstCondition">first</div>
{% endblock %}

{% block sw_second %}
    <div v-else-if="secondCondition">second</div>
    <div v-else>fallback</div>
{% endblock %}
        `).template;

        expect(result).toContain('<div v-if="firstCondition">first</div>');
        expect(result).toContain(
            '<template v-if="(firstCondition)"><!-- Keeps the conditional chain connected across sw-block. --></template>',
        );
        expect(result).toContain('<div v-else-if="secondCondition">second</div>');
        expect(result).toContain('<div v-else>fallback</div>');
        expectTemplateCompiles(result);
    });

    it('uses one guard for previous branches and keeps the following local chain readable', () => {
        const result = transformTemplate(`
{% block sw_first %}
    <div v-if="firstCondition">first</div>
    <div v-else-if="secondCondition">second</div>
{% endblock %}

{% block sw_second %}
    <div v-else-if="thirdCondition">third</div>
    <div v-else>fallback</div>
{% endblock %}
        `).template;

        expect(result).toContain('<div v-if="firstCondition">first</div>');
        expect(result).toContain('<div v-else-if="secondCondition">second</div>');
        expect(result).toContain(
            '<template v-if="(firstCondition) || (secondCondition)"><!-- Keeps the conditional chain connected across sw-block. --></template>',
        );
        expect(result).toContain('<div v-else-if="thirdCondition">third</div>');
        expect(result).toContain('<div v-else>fallback</div>');
        expectTemplateCompiles(result);
    });

    it('inserts guards for cross-block continuations inside nested wrapper elements', () => {
        const result = transformTemplate(`
<section class="wrapper">
    {% block sw_first %}
        <span v-if="isVisible">visible</span>
    {% endblock %}

    {% block sw_second %}
        <span v-else>hidden</span>
    {% endblock %}
</section>
        `).template;

        expect(result).toContain('<span v-if="isVisible">visible</span>');
        expect(result).toContain(
            '<template v-if="(isVisible)"><!-- Keeps the conditional chain connected across sw-block. --></template>',
        );
        expect(result).toContain('<span v-else>hidden</span>');
        expectTemplateCompiles(result);
    });

    it('keeps continuations after an empty extension point block in a normal chain', () => {
        const result = transformTemplate(`
<div v-if="false"></div>

{% block sw_filter_panel_extension_point %}{% endblock %}

<sw-boolean-filter v-else-if="showFilter(filter, 'boolean-filter')" />
<sw-existence-filter v-else-if="showFilter(filter, 'existence-filter')" />
<sw-string-filter v-else />
        `).template;

        expect(result).toContain('<div v-if="false"></div>');
        expect(result).toContain('<sw-block name="sw_filter_panel_extension_point" :data="$dataScope"></sw-block>');
        expect(result).toContain(
            '<template v-if="(false)"><!-- Keeps the conditional chain connected across sw-block. --></template>',
        );
        expect(result).toContain('<sw-boolean-filter v-else-if="showFilter(filter, \'boolean-filter\')" />');
        expect(result).toContain('<sw-existence-filter v-else-if="showFilter(filter, \'existence-filter\')" />');
        expect(result).toContain('<sw-string-filter v-else />');
        expectTemplateCompiles(result);
    });

    it('inserts a guard before normal v-else continuations after a preceding sw-block condition', () => {
        const result = transformTemplate(`
{% block sw_button %}
<mt-button v-if="!deprecated">
    <slot></slot>
</mt-button>
{% endblock %}

<sw-button-deprecated v-else>
    <slot></slot>
</sw-button-deprecated>
        `).template;

        expect(result).toContain('<mt-button v-if="!deprecated">');
        expect(result).toContain(
            '<template v-if="(!deprecated)"><!-- Keeps the conditional chain connected across sw-block. --></template>',
        );
        expect(result).toContain('<sw-button-deprecated v-else>');
        expectTemplateCompiles(result);
    });

    it('keeps guard conditions with double quotes valid through HTML entities', () => {
        const result = transformTemplate(`
{% block sw_first %}
<div v-if="name === 'foo&quot;bar'">quoted</div>
{% endblock %}

{% block sw_second %}
<div v-else>fallback</div>
{% endblock %}
        `).template;

        expect(result).toContain('<div v-if="name === \'foo&quot;bar\'">quoted</div>');
        expect(result).toContain(
            '<template v-if="(name === \'foo&quot;bar\')"><!-- Keeps the conditional chain connected across sw-block. --></template>',
        );
        expect(result).toContain('<div v-else>fallback</div>');
        expectTemplateCompiles(result);
    });

    it('keeps same-block v-if/v-else chains unchanged', () => {
        const result = transformTemplate(`
{% block sw_first %}
    <div v-if="isPrimary">primary</div>
    <div v-else>fallback</div>
{% endblock %}
        `).template;

        expect(result).toContain('<div v-if="isPrimary">primary</div>');
        expect(result).toContain('<div v-else>fallback</div>');
        expectTemplateCompiles(result);
    });

    it('throws for leading v-else cases without a preceding converted v-if block', () => {
        expect(() =>
            transformTemplate(`
{% block sw_first %}
    <div v-else>fallback</div>
{% endblock %}
        `),
        ).toThrow('Cross-block v-else/v-else-if without previous v-if block is not supported by the SFC migration codemod.');
    });
});

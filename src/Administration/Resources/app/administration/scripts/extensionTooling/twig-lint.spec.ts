/**
 * @sw-package framework
 *
 * Behavioural guard for the legacy Twig linting the factory bakes into every
 * extension config. It lints real `.html.twig` fixtures through the generated
 * config, which exercises the committed `vue-eslint-parser` patch that teaches
 * the parser a `{%…%}` Twig token (`patches/vue-eslint-parser`). Admin templates
 * use only the block system (`{% block %}` / `{% endblock %}` / `{% parent %}`),
 * and the patch keeps those markers transparent to `vue/html-indent`: a block
 * whose markers sit at a different indent than the content they wrap must not
 * shift the indentation the rule expects. Without the patch the markers are
 * plain text and the rule flags them — verified: reversing the patch turns the
 * `blockIndent` fixture below from clean into two `vue/html-indent` errors, the
 * same failure that hits ~400 template lines across `src`. The factory is an
 * .mjs module Jest cannot import directly, so one node subprocess builds the
 * config, runs ESLint, and serializes the rule ids per fixture.
 */

import { execFileSync } from 'child_process';
import path from 'path';
import { pathToFileURL } from 'url';

const factoryUrl = pathToFileURL(path.resolve(__dirname, '../../extension-tooling/eslint.mjs')).href;

// A `{% block %}` wrapping one of several sibling elements: its markers stay at
// the container's indent while the wrapped element aligns with its siblings.
// The block markers must be transparent to the indent rule for this to be clean.
const blockIndentTwig = [
    '<div>',
    '    <span>a</span>',
    '{% block sw_middle %}',
    '    <span>b</span>',
    '{% endblock %}',
    '    <span>c</span>',
    '</div>',
    '',
].join('\n');

// `{% block %}` around element content with a keyless v-for: the rule only fires
// if the wrapped Twig parsed into a real element + directive AST, i.e. the
// tokenizer kept `{% %}` out of the element stream.
const contentTwig = [
    '{% block sw_list %}',
    '<ul>',
    '    {% block sw_list_items %}',
    '    <li v-for="item in items">{{ item.label }}</li>',
    '    {% endblock %}',
    '</ul>',
    '{% endblock %}',
    '',
].join('\n');

const probeScript = `
import { ESLint } from 'eslint';

const { shopwareAdminExtension } = await import(${JSON.stringify(factoryUrl)});
const config = shopwareAdminExtension({ tsconfigRootDir: process.cwd() });
// The factory leaves html-indent off on legacy Twig; the Administration's own
// config runs it as an error over the same templates, so turn it on here to
// guard the parser behaviour that keeps those 994 files clean.
config.push({ files: ['**/*.html.twig'], rules: { 'vue/html-indent': ['error', 4, { baseIndent: 0 }] } });
const eslint = new ESLint({ overrideConfigFile: true, overrideConfig: config });

const cases = [
    ['blockIndent', ${JSON.stringify(blockIndentTwig)}],
    ['content', ${JSON.stringify(contentTwig)}],
];
const result = {};
for (const [key, code] of cases) {
    const [report] = await eslint.lintText(code, { filePath: key + '.html.twig' });
    result[key] = report.messages.map((message) => message.ruleId ?? (message.fatal ? 'FATAL' : null));
}

process.stdout.write(JSON.stringify(result));
`;

describe('extension-tooling legacy Twig lint behaviour', () => {
    let result: Record<string, Array<string | null>>;

    beforeAll(() => {
        const output = execFileSync(
            process.execPath,
            [
                '--input-type=module',
                '-e',
                probeScript,
            ],
            {
                cwd: path.resolve(__dirname, '../..'),
                encoding: 'utf8',
            },
        );

        result = JSON.parse(output) as Record<string, Array<string | null>>;
    });

    it('keeps `{% block %}` markers transparent to vue/html-indent', () => {
        expect(result.blockIndent).not.toContain('vue/html-indent');
    });

    it('parses Twig-wrapped content into a traversable AST so functional Vue rules fire', () => {
        expect(result.content).toContain('vue/require-v-for-key');
        expect(result.content).not.toContain('vue/no-parsing-error');
    });
});

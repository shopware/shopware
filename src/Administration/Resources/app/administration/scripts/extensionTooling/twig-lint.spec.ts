/**
 * @sw-package framework
 *
 * Behavioural guard for the legacy Twig linting the factory bakes into every
 * extension config. It lints real `.html.twig` fixtures through the generated
 * config, which exercises the committed `vue-eslint-parser` patch that teaches
 * the parser a `{%…%}` Twig token (`patches/vue-eslint-parser`). That patch is
 * re-derived on every dependency bump; without it, `{% … %}` in an attribute
 * position is tokenized as a run of junk attributes, so `vue/valid-attribute-name`
 * and `vue/no-parsing-error` fire on every Twig conditional (verified: reversing
 * the patch turns the attribute fixture below from clean into 6 findings). The
 * factory is an .mjs module Jest cannot import directly, so one node subprocess
 * builds the config, runs ESLint, and serializes the rule ids per fixture.
 */

import { execFileSync } from 'child_process';
import path from 'path';
import { pathToFileURL } from 'url';

const factoryUrl = pathToFileURL(path.resolve(__dirname, '../../extension-tooling/eslint.mjs')).href;

// `{% %}` where attribute names are expected. With the parser patch the two
// Twig tags are single tokens and `class` is the lone attribute; without it they
// shred into invalid attribute names and a parse error.
const attributeTwig = '<div {% if highlighted %} class="is-highlighted" {% endif %}>body</div>\n';

// `{% %}` around element content plus a Vue interpolation and a keyless v-for:
// the v-for only reports if the wrapped Twig parsed into a real element +
// directive AST, i.e. the tokenizer keeps `{% %}` out of the element stream.
const contentTwig = [
    '{% block sw_card %}',
    '<ul>',
    '    {% block sw_card_items %}',
    '    <li v-for="item in items">{{ item.label }}</li>',
    '    {% endblock %}',
    '</ul>',
    '{% endblock %}',
    '',
].join('\n');

const probeScript = `
import { ESLint } from 'eslint';

const { shopwareAdminExtension } = await import(${JSON.stringify(factoryUrl)});
const eslint = new ESLint({
    overrideConfigFile: true,
    overrideConfig: shopwareAdminExtension({ tsconfigRootDir: process.cwd() }),
});

const cases = [
    ['attribute', ${JSON.stringify(attributeTwig)}],
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

    it('tokenizes `{% %}` in attribute position instead of shredding it into junk attributes', () => {
        expect(result.attribute).not.toContain('vue/valid-attribute-name');
        expect(result.attribute).not.toContain('vue/no-parsing-error');
    });

    it('parses Twig-wrapped content into a traversable AST so functional Vue rules fire', () => {
        expect(result.content).toContain('vue/require-v-for-key');
        expect(result.content).not.toContain('vue/no-parsing-error');
    });
});

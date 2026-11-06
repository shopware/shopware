/**
 * @package admin
 */

const { ESLint } = require('eslint');
const twigVue = require('../index');

const renameDiv = {
    meta: { fixable: 'code' },
    create(context) {
        return context.sourceCode.parserServices.defineTemplateBodyVisitor({
            VElement(node) {
                if (node.name === 'div') {
                    context.report({
                        node,
                        message: 'rename',
                        fix: (fixer) => fixer.replaceTextRange([node.range[0] + 1, node.range[0] + 4], 'section'),
                    });
                }
            },
        });
    },
};

function createESLint({ fix }) {
    return new ESLint({
        overrideConfigFile: true,
        fix,
        overrideConfig: [
            {
                files: ['**/*.html.twig'],
                plugins: { 'twig-vue': twigVue, test: { rules: { 'rename-div': renameDiv } } },
                processor: 'twig-vue/twig-vue',
                languageOptions: { parser: require('vue-eslint-parser') },
                rules: { 'test/rename-div': 'error' },
            },
        ],
    });
}

async function lintTwig(text, { fix }) {
    const [result] = await createESLint({ fix }).lintText(text, { filePath: 'sw-foo.html.twig' });

    return result;
}

describe('twigVuePlugin/lib/processors/twig-vue-processor', () => {
    it('applies fixes at their position in the twig file', async () => {
        const result = await lintTwig('{% block a %}<div></div>{% endblock %}', { fix: true });

        expect(result.output).toBe('{% block a %}<section></div>{% endblock %}');
    });

    it('applies fixes at their position in a twig file with a byte order mark', async () => {
        const result = await lintTwig('﻿{% block a %}<div></div>{% endblock %}', { fix: true });

        expect(result.output).toBe('﻿{% block a %}<section></div>{% endblock %}');
    });

    it('applies fixes at their position in a twig file that already starts with a template tag', async () => {
        const result = await lintTwig('<template>\n<div></div>\n</template>', { fix: true });

        expect(result.output).toBe('<template>\n<section></div>\n</template>');
    });

    it('reports the line of the node in the twig file', async () => {
        const result = await lintTwig('{% block a %}\n<span></span>\n<div></div>\n{% endblock %}', { fix: false });

        expect(result.messages.map((message) => message.line)).toEqual([3]);
    });
});

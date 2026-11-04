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

const eslint = new ESLint({
    overrideConfigFile: true,
    fix: true,
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

async function fixTwig(text) {
    const [result] = await eslint.lintText(text, { filePath: 'sw-foo.html.twig' });

    return result.output;
}

describe('twigVuePlugin/lib/processors/twig-vue-processor', () => {
    it('applies fixes at their position in the twig file', async () => {
        expect(await fixTwig('{% block a %}<div></div>{% endblock %}')).toBe('{% block a %}<section></div>{% endblock %}');
    });

    it('applies fixes at their position in a twig file with a byte order mark', async () => {
        expect(await fixTwig('﻿{% block a %}<div></div>{% endblock %}')).toBe(
            '﻿{% block a %}<section></div>{% endblock %}',
        );
    });
});

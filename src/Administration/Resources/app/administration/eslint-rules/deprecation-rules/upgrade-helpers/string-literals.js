/**
 * @sw-package framework
 *
 * Visits every string of a script or template for the upgrade codemods `no-removed-image-assets` and
 * `no-removed-snippet-keys`: string literals, the fixed parts of template literals, static attribute values and,
 * in `.html.twig` templates, the strings inside `{{ … }}`.
 */

const { isTwigFile } = require('./todo-comment');
const { walk } = require('./component-tags');
const { twigInterpolations } = require('./twig-interpolations');

function isString(node) {
    return (node.type === 'Literal' && typeof node.value === 'string') || node.type === 'TemplateElement';
}

/**
 * The visitors: `handle(node, value, scope)` gets every string node (`Literal`, `TemplateElement` or `VLiteral`),
 * its value, and `'script'` or `'template'`.
 */
function defineStringVisitors(context, handle) {
    const sourceCode = context.sourceCode;
    const valueOf = (node) => (node.type === 'TemplateElement' ? node.value.cooked ?? '' : node.value);
    const visit = (scope) => (node) => {
        if (isString(node)) {
            handle(node, valueOf(node), scope);
        }
    };
    const scriptVisitors = {
        Literal: visit('script'),
        TemplateElement: visit('script'),
    };

    if (!sourceCode.parserServices?.defineTemplateBodyVisitor) {
        return scriptVisitors;
    }

    const templateVisitors = {
        Literal: visit('template'),
        TemplateElement: visit('template'),
        VLiteral(node) {
            if (node.parent.type === 'VAttribute' && !node.parent.directive) {
                handle(node, node.value, 'template');
            }
        },
    };

    if (isTwigFile(context)) {
        // `{{ … }}` is text in `.html.twig` templates
        templateVisitors.VText = (node) => {
            twigInterpolations(sourceCode, node).forEach(({ expression }) => walk(expression, visit('template')));
        };
    }

    return sourceCode.parserServices.defineTemplateBodyVisitor(templateVisitors, scriptVisitors);
}

module.exports = {
    defineStringVisitors,
};

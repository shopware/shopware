/**
 * @sw-package framework
 *
 * Upgrade codemod `filter-computeds`: 6.8 removes the unused `dateFilter` and `assetFilter` computeds of many core
 * components (see `no-removed-filter-computeds.data.js`). Templates can't reach `Shopware.Filter`, so an override
 * that still reads the filter needs its own computed, which works on 6.7 and 6.8 alike:
 *
 * - In a script that overrides or extends such a component, the rule adds
 *   `dateFilter() { return Shopware.Filter.getByName('date'); }` to the computed of the override, when the override,
 *   the template it imports or its `.vue` template reads the filter and the override does not define it itself.
 * - An `.html.twig` override that reads the filter and that no script in its folder imports gets a codemod comment
 *   instead. Templates that a script imports are left to that script, so the result does not depend on the order
 *   in which ESLint lints the files.
 */

const { reportTodo, isTwigFile } = require('./upgrade-helpers/todo-comment');
const { findOverrides } = require('./upgrade-helpers/override-target');
const { walk } = require('./upgrade-helpers/component-tags');
const { templateAnchor } = require('./upgrade-helpers/create-todo-rule');
const {
    declaredMembers,
    propertyName,
    thisMemberName,
    destructuredThisMembers,
    templateMemberReferences,
} = require('./upgrade-helpers/member-usage');
const { importingScripts, templateTargets, overrideTemplateText, templateReads, appendMember } = require('./upgrade-helpers/override-templates');
const { twigInterpolations } = require('./upgrade-helpers/twig-interpolations');
const FILTERS = require('./no-removed-filter-computeds.data');

const ID = 'filter-computeds';
const NAMES = Object.keys(FILTERS);

const computedLines = (name, unit) => [
    `${name}() {`,
    `${unit}return Shopware.Filter.getByName('${FILTERS[name].filter}');`,
    '}',
];

const todoMessage = (component, name) => {
    return `${component} no longer provides ${name}. Add \`${name}() { return Shopware.Filter.getByName('${FILTERS[name].filter}'); }\` `
        + 'to the computed of your override.';
};

const fixMessage = (component, names) => {
    return `${component} no longer provides ${names.join(' and ')} in 6.8. `
        + `Define ${names.length > 1 ? 'them' : 'it'} in the computed of this override.`;
};

function readsInScript(options, name) {
    let found = false;

    walk(options, (node) => {
        if (node.type === 'MemberExpression' && thisMemberName(node) === name) {
            found = true;
        }

        if (node.type === 'VariableDeclarator' && destructuredThisMembers(node).some((member) => member.name === name)) {
            found = true;
        }
    });

    return found;
}

function readsInVueTemplate(ast, name) {
    let found = false;

    if (ast.templateBody) {
        walk(ast.templateBody, (node) => {
            if (node.type === 'VExpressionContainer' && templateMemberReferences(node).some((id) => id.name === name)) {
                found = true;
            }
        });
    }

    return found;
}

/** @type {import('eslint').Rule.RuleModule} */
module.exports = {
    meta: {
        type: 'problem',
        fixable: 'code',
        docs: {
            description: 'Define the dateFilter and assetFilter computeds that 6.8 removes from core components in the overrides that read them',
        },
        schema: [],
    },

    /** @param {import('eslint').Rule.RuleContext} context */
    create(context) {
        const sourceCode = context.sourceCode;

        function checkOverride({ target, options, node }) {
            const names = NAMES.filter((name) => FILTERS[name].components.includes(target));

            if (names.length === 0 || !options) {
                return;
            }

            const declared = new Set(declaredMembers(options).map((member) => member.name));
            let template;
            const readsInTemplate = (name) => {
                template ??= overrideTemplateText(context, options) ?? '';

                return templateReads(template, name) || readsInVueTemplate(sourceCode.ast, name);
            };
            const missing = names.filter((name) => !declared.has(name) && (readsInScript(options, name) || readsInTemplate(name)));

            if (missing.length === 0) {
                return;
            }

            const computed = options.properties.find((property) => propertyName(property) === 'computed');

            if (computed && computed.value.type !== 'ObjectExpression') {
                missing.forEach((name) => {
                    reportTodo(context, { id: ID, message: todoMessage(target, name), range: computed.range, scope: 'script' });
                });

                return;
            }

            // The new members as lines; the last one gets its comma from `appendMember` unless `trailingComma` is set
            const members = (unit, trailingComma = false) => missing.flatMap((name, index) => {
                const lines = computedLines(name, unit);

                if (index < missing.length - 1 || trailingComma) {
                    lines[lines.length - 1] = '},';
                }

                return index < missing.length - 1 ? [...lines, ''] : lines;
            });
            let fix;

            if (computed) {
                const last = computed.value.properties[computed.value.properties.length - 1];
                const multiLine = Boolean(last) && last.loc.start.line !== last.loc.end.line;

                fix = appendMember(sourceCode, computed.value, (unit) => members(unit), { blankLine: multiLine });
            } else {
                fix = appendMember(
                    sourceCode,
                    options,
                    (unit) => ['computed: {', ...members(unit, true).map((line) => (line ? `${unit}${line}` : line)), '}'],
                    { blankLine: true },
                );
            }

            context.report({ node, message: fixMessage(target, missing), fix });
        }

        const scriptVisitors = {
            Program() {
                findOverrides(context).forEach(checkOverride);
            },
        };

        if (!sourceCode.parserServices?.defineTemplateBodyVisitor || !isTwigFile(context)) {
            return scriptVisitors;
        }

        // A script in the folder imports this template and adds the computed there.
        if (importingScripts(context).length > 0) {
            return scriptVisitors;
        }

        const targets = templateTargets(context);
        const reported = new Set();
        const check = (references) => {
            for (const identifier of references) {
                const name = identifier.name;
                const target = FILTERS[name] && targets.find((candidate) => FILTERS[name].components.includes(candidate));

                if (!target || reported.has(name)) {
                    continue;
                }

                reported.add(name);
                reportTodo(context, {
                    id: ID,
                    message: todoMessage(target, name),
                    range: templateAnchor(identifier).range,
                    scope: 'template',
                });
            }
        };

        return sourceCode.parserServices.defineTemplateBodyVisitor(
            {
                VExpressionContainer(node) {
                    check(templateMemberReferences(node));
                },
                // `{{ … }}` is text in `.html.twig` templates
                VText(node) {
                    twigInterpolations(sourceCode, node).forEach(({ references }) => check(references));
                },
            },
            scriptVisitors,
        );
    },
};

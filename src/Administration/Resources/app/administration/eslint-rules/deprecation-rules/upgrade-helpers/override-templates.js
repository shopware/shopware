/**
 * @sw-package framework
 *
 * Shared logic of the script rewrite codemods `no-removed-filter-computeds` and `no-order-list-delivery-helpers`:
 * links an override script to the template it imports and back, and adds members to an options object.
 *
 * A template is imported by any script in its folder, not only by `index.js`, for example
 * `sw-order-list/sw-order-list.js` with `import template from './sw-order-list.html.twig'`.
 * The files are only read, never written.
 */

const fs = require('fs');
const path = require('path');
const { twigTargets } = require('./twig-target');

const CORE_COMPONENT_NAME = /^sw-[a-z0-9-]+$/;
const SCRIPT_FILE = /\.[cm]?[jt]s$/;
const SPEC_FILE = /\.spec\.[cm]?[jt]s$/;
const OVERRIDE_CALL = /Component\s*\.\s*(override|extend)\(\s*(['"`])([\w-]+)\2\s*(?:,\s*(['"`])([\w-]+)\4)?/g;
const REGISTER_CALL = /Component\s*\.\s*register\(/;
const TEMPLATE_COMMENTS = /\{#[\s\S]*?#\}|<!--[\s\S]*?-->/g;

function physicalFilename(context) {
    return context.physicalFilename ?? context.filename ?? '';
}

function escapeRegExp(text) {
    return text.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

/**
 * The scripts in the folder of the linted template that import it.
 */
function importingScripts(context) {
    const filename = physicalFilename(context);
    const directory = path.dirname(filename);
    const importsTemplate = new RegExp(`(['"\`])\\./${escapeRegExp(path.basename(filename))}\\1`);
    let names;

    try {
        names = fs.readdirSync(directory);
    } catch {
        return [];
    }

    return names
        .filter((name) => SCRIPT_FILE.test(name) && !SPEC_FILE.test(name))
        .flatMap((name) => {
            const file = path.join(directory, name);

            try {
                const source = fs.readFileSync(file, 'utf8');

                return importsTemplate.test(source) ? [{ file, source }] : [];
            } catch {
                return [];
            }
        });
}

/**
 * The core components the linted template overrides.
 *
 * Uses the scripts that import the template: the target of their `Component.override/extend` call, nothing for a
 * script that registers its own component, and the core component name of the file or folder for an
 * `export default {…}` that is passed to an override elsewhere. Without an importing script it falls back to
 * `twigTargets()`.
 */
function templateTargets(context) {
    const scripts = importingScripts(context);

    if (scripts.length === 0) {
        return twigTargets(context);
    }

    const filename = physicalFilename(context);
    const names = [path.basename(filename).replace(/(\.html)?\.twig$/, ''), path.basename(path.dirname(filename))]
        .filter((name) => CORE_COMPONENT_NAME.test(name));
    const targets = new Set();

    for (const { source } of scripts) {
        const calls = [...source.matchAll(OVERRIDE_CALL)];

        if (calls.length > 0) {
            calls.forEach((match) => targets.add(match[1] === 'extend' && match[5] ? match[5] : match[3]));
        } else if (!REGISTER_CALL.test(source)) {
            names.forEach((name) => targets.add(name));
        }
    }

    return [...targets];
}

/**
 * Whether one of the scripts declares a method, computed or data entry `name` itself.
 */
function scriptsDeclare(scripts, name) {
    const escaped = escapeRegExp(name);
    const method = new RegExp(`(?:^|[\\s,{])(?:async\\s+)?${escaped}\\s*\\([^)]*\\)\\s*\\{`, 'm');
    const property = new RegExp(`(?:^|[\\s,{])['"]?${escaped}['"]?\\s*:`, 'm');

    return scripts.some(({ source }) => method.test(source) || property.test(source));
}

/**
 * The text of the template an override passes as `template`: an imported `.twig`/`.html` file next to the
 * script or an inline string.
 */
function overrideTemplateText(context, options) {
    const property = options.properties.find(
        (candidate) => candidate.type === 'Property' && !candidate.computed && candidate.key.type === 'Identifier' && candidate.key.name === 'template',
    );

    if (!property) {
        return null;
    }

    const value = property.value;

    if (value.type === 'Literal' && typeof value.value === 'string') {
        return value.value;
    }

    if (value.type === 'TemplateLiteral') {
        return value.quasis.map((quasi) => quasi.value.cooked ?? '').join('');
    }

    if (value.type !== 'Identifier') {
        return null;
    }

    const declaration = context.sourceCode.ast.body.find(
        (statement) => statement.type === 'ImportDeclaration'
            && statement.specifiers.some((specifier) => specifier.type === 'ImportDefaultSpecifier' && specifier.local.name === value.name),
    );
    const source = declaration?.source.value;

    if (typeof source !== 'string' || !source.startsWith('.') || !/\.(twig|html)$/.test(source)) {
        return null;
    }

    try {
        return fs.readFileSync(path.resolve(path.dirname(physicalFilename(context)), source), 'utf8');
    } catch {
        return null;
    }
}

/**
 * Whether a template reads `name` outside of comments.
 */
function templateReads(text, name) {
    return new RegExp(`(?<![\\w$.])${escapeRegExp(name)}\\b`).test(text.replace(TEMPLATE_COMMENTS, ''));
}

function lineStartIndex(text, index) {
    return text.lastIndexOf('\n', index - 1) + 1;
}

function indentationOfLine(text, index) {
    return /^[ \t]*/.exec(text.slice(lineStartIndex(text, index)))[0];
}

/**
 * Adds a member at the end of an object literal, with the indentation of its members and a trailing comma.
 * The member is given as lines relative to its own indentation; `indent` in the lines stands for one level.
 * `blankLine` separates it from the previous member. Returns the `fix` function for `context.report()`.
 */
function appendMember(sourceCode, object, memberLines, { blankLine = false } = {}) {
    const text = sourceCode.text;
    const objectIndent = indentationOfLine(text, object.range[0]);
    const last = object.properties[object.properties.length - 1];

    if (!last) {
        const unit = '    ';
        const lines = memberLines(unit).map((line, index) => (index === 0 || line === '' ? line : `${objectIndent}${unit}${line}`));

        return (fixer) => fixer.replaceText(object, `{\n${objectIndent}${unit}${lines.join('\n')},\n${objectIndent}}`);
    }

    const memberIndent = indentationOfLine(text, last.range[0]);
    const unit = memberIndent.length > objectIndent.length ? memberIndent.slice(objectIndent.length) : '    ';
    const lines = memberLines(unit).map((line, index) => (index === 0 || line === '' ? line : `${memberIndent}${line}`));
    const comma = sourceCode.getTokenAfter(last);
    const hasTrailingComma = comma && comma.value === ',' && comma.range[1] <= object.range[1];
    let position = hasTrailingComma ? comma.range[1] : last.range[1];
    const lineEnd = text.indexOf('\n', position);

    // Keep a comment that ends the line of the last member on that line
    if (hasTrailingComma && lineEnd !== -1 && /^\s*\/\/.*$/.test(text.slice(position, lineEnd))) {
        position = lineEnd;
    }

    const member = `${blankLine ? '\n' : ''}\n${memberIndent}${lines.join('\n')}`;

    return (fixer) => fixer.insertTextAfterRange([position, position], hasTrailingComma ? `${member},` : `,${member}`);
}

module.exports = {
    importingScripts,
    templateTargets,
    scriptsDeclare,
    overrideTemplateText,
    templateReads,
    appendMember,
};

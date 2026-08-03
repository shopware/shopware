import type { Node as TsNode, PropertyAccessExpression } from 'ts-morph';
import { Node, SyntaxKind } from 'ts-morph';
import { attrsIdent, emitIdent, routeIdent, routerIdent, slotsIdent, tIdent } from './identifiers';
import { createIdentifierTemplate, identTemplate, isIdentifierToken } from './identifier-template';
import type { IdentifierTemplateValue, IdentifierToken, ScriptSnippet } from './identifier-template';
import type { CodeSnippet, RewriteContext, RewriteSnippetKind, UsedComposables, WatchProp } from './types';
import {
    createWrappedSnippetSource,
    getDirectThisPropertyName,
    getSnippetCallExpressions,
    getSnippetPropertyAccesses,
    getThisRefName,
    isNodeInsideSnippet,
} from './ast';
import { buildPropertyAccess, isDefined } from './helpers';

export function buildWatchSource(name: string, propNames: Set<string>, injectNames: Set<string>): ScriptSnippet {
    if (propNames.has(name)) {
        return buildPropertyAccess('props', name);
    }

    if (name === '$route') {
        // The route object keeps its identity across navigations. Watch a
        // snapshot so changes trigger and Vue still provides distinct to/from
        // values to the handler.
        return identTemplate`({ ...${routeIdent}, params: { ...${routeIdent}.params }, query: { ...${routeIdent}.query } })`;
    }

    if (injectNames.has(name)) {
        // inject() can return a plain service or a Ref; unref() tracks both
        // forms when the injected value is used as a watch source.
        return `unref(${name})`;
    }

    return `${name}.value`;
}

export function collectThisRefNames(snippets: CodeSnippet[]): string[] {
    const names = new Set<string>();

    for (const snippet of snippets) {
        for (const node of getSnippetPropertyAccesses(snippet)) {
            const refName = getThisRefName(node);

            if (refName) {
                names.add(refName);
            }
        }
    }

    return [...names];
}

export function detectUsedComposables(snippets: CodeSnippet[], watchProps: WatchProp[]): UsedComposables {
    const usedComposables: UsedComposables = {
        needsRouter: false,
        needsRoute: watchProps.some((prop) => prop.name === '$route'),
        needsNextTick: false,
        needsSlots: false,
        needsI18n: false,
        needsEmit: false,
        needsAttrs: false,
    };

    for (const snippet of snippets) {
        for (const node of getSnippetPropertyAccesses(snippet)) {
            switch (getDirectThisPropertyName(node)) {
                case '$router':
                    usedComposables.needsRouter = true;
                    break;
                case '$route':
                    usedComposables.needsRoute = true;
                    break;
                case '$nextTick':
                    usedComposables.needsNextTick = true;
                    break;
                case '$slots':
                    usedComposables.needsSlots = true;
                    break;
                case '$tc':
                case '$t':
                    usedComposables.needsI18n = true;
                    break;
                case '$emit':
                    usedComposables.needsEmit = true;
                    break;
                case '$attrs':
                    usedComposables.needsAttrs = true;
                    break;
                default:
                    break;
            }
        }
    }

    return usedComposables;
}

export function hasDirectThisPropertyUsage(snippets: CodeSnippet[], propertyName: string): boolean {
    return snippets.some((snippet) =>
        getSnippetPropertyAccesses(snippet).some((node) => getDirectThisPropertyName(node) === propertyName),
    );
}

export function collectEmittedEventNames(snippets: CodeSnippet[]): string[] {
    const names = new Set<string>();

    for (const snippet of snippets) {
        for (const node of getSnippetCallExpressions(snippet)) {
            const expression = node.getExpression();
            const firstArgument = node.getArguments()[0];

            // Example: `this.$emit('save')`
            if (
                Node.isPropertyAccessExpression(expression) &&
                getDirectThisPropertyName(expression) === '$emit' &&
                firstArgument?.isKind(SyntaxKind.StringLiteral)
            ) {
                names.add(firstArgument.asKindOrThrow(SyntaxKind.StringLiteral).getLiteralValue());
            }
        }
    }

    return [...names];
}

/**
 * Names that `rewriteThisInBody` can turn into a setup-safe expression. Used by
 * `findUnsupportedThisUsage` to decide whether a `this.<name>` access would be
 * emitted verbatim (and therefore break at runtime).
 */
function isSupportedThisPropertyName(name: string, ctx: RewriteContext): boolean {
    return (
        name === '$emit' ||
        name === '$router' ||
        name === '$route' ||
        name === '$nextTick' ||
        name === '$slots' ||
        name === '$props' ||
        name === '$attrs' ||
        name === '$tc' ||
        name === '$t' ||
        name === '$refs' ||
        name === '$el' ||
        name === '$store' ||
        name === '$parent' ||
        name === '$root' ||
        name === '$options' ||
        name === '$forceUpdate' ||
        ctx.propNames.has(name) ||
        ctx.dataNames.has(name) ||
        ctx.computedNames.has(name) ||
        ctx.methodNames.has(name) ||
        ctx.injectNames.has(name)
    );
}

/**
 * Detects a `this.<method>()` call inside a data initializer. In setup those
 * methods become `const` declarations emitted after the data refs, so calling
 * them from a ref initializer would hit a temporal-dead-zone ReferenceError.
 */
export function findDataInitializerMethodCall(valueText: string, methodNames: Set<string>): string | null {
    for (const callExpression of getSnippetCallExpressions({ text: valueText, kind: 'expression' })) {
        const expression = callExpression.getExpression();

        if (!Node.isPropertyAccessExpression(expression)) {
            continue;
        }

        const thisPropertyName = getDirectThisPropertyName(expression);
        if (thisPropertyName && methodNames.has(thisPropertyName)) {
            return thisPropertyName;
        }
    }

    return null;
}

/**
 * Returns a human-readable reason when a snippet still depends on the Options
 * API instance in a way that cannot be rewritten into setup: dynamic
 * `this[key]` access, a bare `this` reference (aliasing, destructuring,
 * `.bind(this)`), or an unknown `this.<name>` property. Callers drop the member
 * and record the reason instead of emitting non-equivalent code.
 */
export function findUnsupportedThisUsage(snippet: CodeSnippet, ctx: RewriteContext): string | null {
    const { sourceFile, snippetStart, snippetEnd } = createWrappedSnippetSource(snippet.text, snippet.kind);
    const inSnippet = (node: TsNode): boolean => isNodeInsideSnippet(node, snippetStart, snippetEnd);

    const dynamicAccess = sourceFile
        .getDescendantsOfKind(SyntaxKind.ElementAccessExpression)
        .filter(inSnippet)
        .some((node) => node.getExpression().isKind(SyntaxKind.ThisKeyword));
    if (dynamicAccess) {
        return 'dynamic this access';
    }

    const bareThis = sourceFile
        .getDescendantsOfKind(SyntaxKind.ThisKeyword)
        .filter(inSnippet)
        .find((node) => !isThisPropertyObject(node));
    if (bareThis) {
        const parent = bareThis.getParent();

        // `const vm = this` keeps a named alias of the instance; other bare
        // `this` uses (destructuring, `.bind(this)`, `scope: this`) are grouped
        // under a single reason.
        return Node.isVariableDeclaration(parent) && Node.isIdentifier(parent.getNameNode()) ? 'this alias' : 'bare this';
    }

    for (const propertyAccess of getSnippetPropertyAccesses(snippet)) {
        if (getThisRefName(propertyAccess)) {
            continue;
        }

        const name = getDirectThisPropertyName(propertyAccess);
        if (!name || isSupportedThisPropertyName(name, ctx)) {
            continue;
        }

        return `unknown this property '${name}'`;
    }

    return null;
}

/** true when the `this` keyword is the object of a `this.<name>` access. */
function isThisPropertyObject(thisNode: TsNode): boolean {
    const parent = thisNode.getParent();

    return Node.isPropertyAccessExpression(parent) && parent.getExpression().getStart() === thisNode.getStart();
}

export function rewriteThisInBody(bodyText: string, ctx: RewriteContext, kind: RewriteSnippetKind = 'body'): ScriptSnippet {
    const { sourceFile, snippetStart, snippetEnd } = createWrappedSnippetSource(bodyText, kind);
    // Only property accesses are rewritten here; instance dependencies that
    // cannot be rewritten (bare `this`, `this[key]`, unknown `this.<name>`) are
    // filtered out earlier via findUnsupportedThisUsage.
    const replacements = sourceFile
        // Example: `this.product.name` contains `this.product` and `this.product.name` property accesses.
        .getDescendantsOfKind(SyntaxKind.PropertyAccessExpression)
        .filter((node) => isNodeInsideSnippet(node, snippetStart, snippetEnd))
        .map((node) => {
            const replacement = buildThisReplacement(node, ctx);

            if (!replacement) {
                return undefined;
            }

            return {
                start: node.getStart() - snippetStart,
                end: node.getEnd() - snippetStart,
                replacement,
            };
        })
        .filter(isDefined)
        // Replace longest nested accesses first. For example, `this.$refs.foo`
        // should become `foo.value` once, not receive a second replacement for
        // the inner `this.$refs` access.
        .sort((a, b) => b.start - a.start || b.end - a.end);

    const acceptedReplacements: typeof replacements = [];
    let lastReplacedStart = bodyText.length + 1;

    for (const replacement of replacements) {
        const { start, end } = replacement;
        if (end > lastReplacedStart) {
            continue;
        }

        acceptedReplacements.push(replacement);
        lastReplacedStart = start;
    }

    if (!acceptedReplacements.some(({ replacement }) => isIdentifierToken(replacement))) {
        let result = bodyText;

        for (const { start, end, replacement } of acceptedReplacements) {
            result = result.slice(0, start) + replacement + result.slice(end);
        }

        return result;
    }

    const parts: IdentifierTemplateValue[] = [];
    let cursor = 0;

    acceptedReplacements
        .sort((a, b) => a.start - b.start)
        .forEach(({ start, end, replacement }) => {
            if (start > cursor) {
                parts.push(bodyText.slice(cursor, start));
            }

            parts.push(replacement);
            cursor = end;
        });

    if (cursor < bodyText.length) {
        parts.push(bodyText.slice(cursor));
    }

    return createIdentifierTemplate(parts);
}

function buildThisReplacement(node: PropertyAccessExpression, ctx: RewriteContext): string | IdentifierToken | null {
    const refName = getThisRefName(node);

    if (refName) {
        return `${refName}.value`;
    }

    const name = getDirectThisPropertyName(node);

    if (!name) {
        return null;
    }

    switch (name) {
        case '$emit':
            return emitIdent;
        case '$router':
            return routerIdent;
        case '$route':
            return routeIdent;
        case '$nextTick':
            return 'nextTick';
        case '$slots':
            return slotsIdent;
        case '$props':
            return 'props';
        case '$attrs':
            return attrsIdent;
        case '$tc':
        case '$t':
            return tIdent;
        case '$el':
            // There is no setup-safe equivalent for root DOM access; this is a
            // transitional bridge. collectPlaceholderApiReasons keeps the
            // migration partial so the placeholder is reviewed after generation.
            return '/* TODO: $el */ getCurrentInstance()?.proxy?.$el';
        case '$store':
            // Vuex access needs a store-specific Pinia/composable migration.
            // Throwing prevents generated code from silently shipping with a
            // non-functional placeholder.
            return "/* TODO: migrate $store to composable */\n        (() => { throw new Error('$store used here — replace with the appropriate Pinia store or composable before shipping'); })()";
        case '$parent':
            // Placeholder rewrites for instance APIs change runtime behavior;
            // collectPlaceholderApiReasons reports them so the migration stays
            // partial.
            return '/* TODO: $parent */ undefined';
        case '$root':
            return '/* TODO: $root */ undefined';
        case '$options':
            return '/* TODO: $options */ {}';
        case '$forceUpdate':
            return '/* TODO: $forceUpdate */ (() => {})';
        default:
            break;
    }

    if (ctx.propNames.has(name)) {
        return buildPropertyAccess('props', name);
    }

    if (ctx.dataNames.has(name) || ctx.computedNames.has(name)) {
        return `${name}.value`;
    }

    if (ctx.methodNames.has(name) || ctx.injectNames.has(name)) {
        return name;
    }

    // Unknown `this.<name>` is left unrewritten here; findUnsupportedThisUsage
    // drops the containing member before emission, so it never reaches output.
    return null;
}

import type { PropertyAccessExpression } from 'ts-morph';
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

export function rewriteThisInBody(bodyText: string, ctx: RewriteContext, kind: RewriteSnippetKind = 'body'): ScriptSnippet {
    const { sourceFile, snippetStart, snippetEnd } = createWrappedSnippetSource(bodyText, kind);
    // TODO: Silent ignore: only property accesses are inspected. Bare `this`,
    // element access (`this[key]`), destructuring, aliases, and `.bind(this)`
    // can remain in generated setup code without a blocker.
    const replacements = sourceFile
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
            // transitional bridge that must be reviewed after generation.
            // TODO: Silent ignore: $el placeholder usage still reports fully
            // migratable unless a separate blocker is added.
            return '/* TODO: $el */ getCurrentInstance()?.proxy?.$el';
        case '$store':
            // Vuex access needs a store-specific Pinia/composable migration.
            // Throwing prevents generated code from silently shipping with a
            // non-functional placeholder.
            return "/* TODO: migrate $store to composable */\n        (() => { throw new Error('$store used here — replace with the appropriate Pinia store or composable before shipping'); })()";
        case '$parent':
            // TODO: Silent ignore: placeholder rewrites for instance APIs
            // change runtime behavior but do not currently force partial
            // migration status.
            return '/* TODO: $parent */ undefined';
        case '$root':
            // TODO: Silent ignore: placeholder rewrites for instance APIs
            // change runtime behavior but do not currently force partial
            // migration status.
            return '/* TODO: $root */ undefined';
        case '$options':
            // TODO: Silent ignore: placeholder rewrites for instance APIs
            // change runtime behavior but do not currently force partial
            // migration status.
            return '/* TODO: $options */ {}';
        case '$forceUpdate':
            // TODO: Silent ignore: placeholder rewrites for instance APIs
            // change runtime behavior but do not currently force partial
            // migration status.
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

    // TODO: Silent ignore: unknown `this.<name>` accesses are left in setup
    // output instead of becoming a blocker.
    return null;
}

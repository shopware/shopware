import type { Node as TsNode, PropertyAccessExpression } from 'ts-morph';
import { Node, SyntaxKind } from 'ts-morph';
import type { ResolvedIdentifiers } from './resolve-identifiers';
import type { CodeSnippet, RewriteContext, RewriteSnippetKind, UsedComposables, WatchProp } from './types';
import type { LocalBindingScope } from './ast';
import {
    collectLocalBindingScopes,
    createWrappedSnippetSource,
    getDirectThisPropertyName,
    getSnippetCallExpressions,
    getSnippetPropertyAccesses,
    getThisRefName,
    isCoveredByBindingScope,
    isNodeInsideSnippet,
} from './ast';
import { buildPropertyAccess, getWatchRootName, isDefined, parseWatchPath } from './helpers';

export function buildWatchSource(
    name: string,
    propNames: Set<string>,
    injectNames: Set<string>,
    names: ResolvedIdentifiers,
): string {
    // Keys whose segments cannot be written as a property access never reach the
    // emitter — collectSupportedWatchProps drops them — so an unparsable key here
    // can only be a plain member name.
    const { root, propertyPath } = parseWatchPath(name) ?? { root: name, propertyPath: [] };
    const rootSource = buildWatchRootSource(root, propertyPath.length > 0, propNames, injectNames, names);

    // Vue's path getter stops the walk on any falsy intermediate value, `?.`
    // only on a nullish one. The two agree everywhere except on a falsy
    // non-nullish intermediate (`0`, `''`, `false`), which is not a shape real
    // components watch a property off.
    return propertyPath.reduce((source, segment) => `${source}?.${segment}`, rootSource);
}

function buildWatchRootSource(
    name: string,
    isPathWatcher: boolean,
    propNames: Set<string>,
    injectNames: Set<string>,
    names: ResolvedIdentifiers,
): string {
    if (propNames.has(name)) {
        return buildPropertyAccess('props', name);
    }

    if (name === '$route') {
        // The route object keeps its identity across navigations. Watch a
        // snapshot so changes trigger and Vue still provides distinct to/from
        // values to the handler. A path watcher reads a value out of the current
        // route instead, which changes on its own.
        return isPathWatcher
            ? names.route
            : `({ ...${names.route}, params: { ...${names.route}.params }, query: { ...${names.route}.query } })`;
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
        needsRoute: watchProps.some((prop) => getWatchRootName(prop.name) === '$route'),
        needsNextTick: false,
        needsSlots: false,
        needsTranslate: false,
        needsTranslationExists: false,
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
                    usedComposables.needsTranslate = true;
                    break;
                case '$te':
                    usedComposables.needsTranslationExists = true;
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
        name === '$te' ||
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
    const { sourceFile, snippetStart, snippetEnd } = createWrappedSnippetSource(
        snippet.text,
        snippet.kind,
        snippet.paramsText,
    );
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

    const bindingScopes = collectLocalBindingScopes(sourceFile);

    for (const propertyAccess of sourceFile.getDescendantsOfKind(SyntaxKind.PropertyAccessExpression).filter(inSnippet)) {
        const shadowedRoot = findShadowedRewriteRoot(propertyAccess, ctx, bindingScopes);
        if (shadowedRoot) {
            return `rewrite target '${shadowedRoot}' is shadowed by a local binding`;
        }

        if (getThisRefName(propertyAccess)) {
            continue;
        }

        const name = getDirectThisPropertyName(propertyAccess);
        if (!name || isSupportedThisPropertyName(name, ctx)) {
            continue;
        }

        // The component declared this name, so it is not unknown — it was
        // dropped earlier. Reporting it as unknown would point at the reference
        // instead of at the member that actually needs the manual migration.
        if (ctx.declaredMemberNames.has(name)) {
            return `dropped member '${name}'`;
        }

        return `unknown this property '${name}'`;
    }

    return null;
}

/**
 * The bare identifier a `this.<name>` rewrite would root its replacement in, or
 * null when the replacement is not rooted in one — a TODO placeholder, or a
 * binding `resolve-identifiers.ts` names. Those generated names are picked
 * around every binding the module declares, parameters and locals included, so
 * they cannot be shadowed and are deliberately absent here.
 */
function getRewriteRootName(node: PropertyAccessExpression, ctx: RewriteContext): string | null {
    const refName = getThisRefName(node);

    if (refName) {
        return refName;
    }

    const name = getDirectThisPropertyName(node);

    if (!name) {
        return null;
    }

    if (name === '$nextTick') {
        return 'nextTick';
    }

    if (name === '$props' || ctx.propNames.has(name)) {
        return 'props';
    }

    if (ctx.dataNames.has(name) || ctx.computedNames.has(name) || ctx.methodNames.has(name) || ctx.injectNames.has(name)) {
        return name;
    }

    return null;
}

/**
 * The rewrite replaces `this.<name>` with a bare identifier, which resolves in
 * the scope the access sits in — not in the setup scope the generated binding
 * lives in. A local of the same name therefore captures the rewritten access and
 * turns `this.action = …` inside `runAction(action)` into an assignment to the
 * parameter. Callers drop the member instead.
 */
function findShadowedRewriteRoot(
    node: PropertyAccessExpression,
    ctx: RewriteContext,
    bindingScopes: LocalBindingScope[],
): string | null {
    const rootName = getRewriteRootName(node, ctx);

    if (!rootName) {
        return null;
    }

    return isCoveredByBindingScope(bindingScopes, rootName, node.getStart(), node.getEnd()) ? rootName : null;
}

/** true when the `this` keyword is the object of a `this.<name>` access. */
function isThisPropertyObject(thisNode: TsNode): boolean {
    const parent = thisNode.getParent();

    return Node.isPropertyAccessExpression(parent) && parent.getExpression().getStart() === thisNode.getStart();
}

export function rewriteThisInBody(
    bodyText: string,
    ctx: RewriteContext,
    names: ResolvedIdentifiers,
    kind: RewriteSnippetKind = 'body',
): string {
    const { sourceFile, snippetStart, snippetEnd } = createWrappedSnippetSource(bodyText, kind);
    // Only property accesses are rewritten here; instance dependencies that
    // cannot be rewritten (bare `this`, `this[key]`, unknown `this.<name>`) are
    // filtered out earlier via findUnsupportedThisUsage.
    const replacements = sourceFile
        // Example: `this.product.name` contains `this.product` and `this.product.name` property accesses.
        .getDescendantsOfKind(SyntaxKind.PropertyAccessExpression)
        .filter((node) => isNodeInsideSnippet(node, snippetStart, snippetEnd))
        .map((node) => {
            const replacement = buildThisReplacement(node, ctx, names);

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

    // Accepted replacements are ordered longest-first, so splicing them in that
    // order keeps every earlier offset valid.
    let result = bodyText;

    for (const { start, end, replacement } of acceptedReplacements) {
        result = result.slice(0, start) + replacement + result.slice(end);
    }

    return result;
}

function buildThisReplacement(
    node: PropertyAccessExpression,
    ctx: RewriteContext,
    names: ResolvedIdentifiers,
): string | null {
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
            return names.emit;
        case '$router':
            return names.router;
        case '$route':
            return names.route;
        case '$nextTick':
            return 'nextTick';
        case '$slots':
            return names.slots;
        case '$props':
            return 'props';
        case '$attrs':
            return names.attrs;
        case '$tc':
        case '$t':
            return names.t;
        case '$te':
            // The legacy `$te(key, locale?)` maps to the composer's `te` with the
            // same signature.
            return names.te;
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

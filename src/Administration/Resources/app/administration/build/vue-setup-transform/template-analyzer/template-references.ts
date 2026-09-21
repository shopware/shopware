/**
 * @sw-package framework
 */

/**
 * Walks the Vue template AST and tracks nested scopes.
 *
 * Slot scopes and v-for aliases introduce template-local names; these helpers collect the setup
 * references of whole subtrees while respecting that nesting - as names, and as the occurrence sites
 * the override rewrite replaces - and identify the sw-block elements the analyzers care about.
 */

import { NodeTypes } from '@vue/compiler-dom';
import type {
    DirectiveNode as CoreDirectiveNode,
    ElementNode as CoreElementNode,
    TemplateChildNode,
} from '@vue/compiler-dom';
import {
    type OccurrenceExpansion,
    addPatternNames,
    collectExpressionOccurrences,
    collectPatternOccurrences,
    parseBindingPattern,
} from '../flow-analysis';

/**
 * The setup references an override slot reads, as names and as the exact sites a rewrite must touch.
 *
 * Both come out of one walk: `references` decides *which* bindings an override has to forward, and
 * `occurrences` is where the lowerer rewrites each of them to its slot-scope path.
 */
type TemplateReferences = {
    references: Set<string>;
    occurrences: TemplateOccurrence[];
    /**
     * Expressions whose offsets cannot be mapped back into the template, with the names they read.
     *
     * Vue decodes HTML entities while parsing, so an expression written as `count &lt; max` reaches us
     * shorter than its source and every offset inside it would land on the wrong character. Reading such
     * an expression is fine; rewriting it is not, so the analyzer rejects one that reads a forwarded
     * binding instead of emitting a corrupt edit.
     */
    unmappableExpressions: UnmappableExpression[];
};

/**
 * One reference site inside `<sw-block extends>` content, in template coordinates.
 *
 * `expansion` says how much syntax the replacement has to reproduce: a plain occurrence is swapped for
 * the path, a shorthand object property (`{ info }`) has to keep its key, and Vue's same-name binding
 * shorthand (`:info`) has no value range at all - the replacement is the `="…"` that gives it one, so
 * `start`/`end` sit at the end of the directive.
 */
type TemplateOccurrence = {
    name: string;
    start: number;
    end: number;
    expansion: OccurrenceExpansion | 'same-name-shorthand';
};

type UnmappableExpression = {
    names: Set<string>;
    offset: number;
};

/** The shape of every Vue node that carries expression text: interpolations, directive args and exps. */
type TemplateExpression = {
    content: string;
    loc: { source: string; start: { offset: number }; end: { offset: number } };
};

/** Accumulates one walk's findings; mutated in place so a single result collects the whole subtree. */
type ReferenceCollector = TemplateReferences;

type DirectiveNode = CoreDirectiveNode & {
    arg?: TemplateExpression & { isStatic?: boolean };
    exp?: TemplateExpression;
    forParseResult?: {
        value?: TemplateExpression;
        key?: TemplateExpression;
        index?: TemplateExpression;
        source?: TemplateExpression;
    };
    // Optional to match `@vue/compiler-core` (declares `rawName?: string`); the consumer in
    // sw-block-bindings falls back to `v-${name}`, and that fallback must stay reachable per the type.
    rawName?: string;
};

type ElementNode = CoreElementNode & {
    props: Array<CoreElementNode['props'][number] | DirectiveNode>;
    children: TemplateChildNode[];
};

/**
 * Returns the binding-pattern nodes a directive contributes, in evaluation order.
 *
 * A v-slot contributes its single expression (`#default="{ item }"`); a v-for contributes its alias
 * slots (`(value, key, index)`) left to right, so an earlier alias shadows a later default.
 */
function getBindingPatternSources(directive: DirectiveNode | undefined): TemplateExpression[] {
    if (!directive) {
        return [];
    }

    if (directive.name === 'for') {
        const parseResult = directive.forParseResult;

        return [
            parseResult?.value,
            parseResult?.key,
            parseResult?.index,
        ].filter((expression) => Boolean(expression?.content)) as TemplateExpression[];
    }

    return directive.exp?.content ? [directive.exp] : [];
}

/**
 * Returns the names a directive's binding patterns declare (v-slot props, v-for aliases).
 *
 * An unparseable v-for alias falls back to its raw text, which is what a plain `v-for="item in list"`
 * alias already looks like; an unparseable v-slot pattern is left to Vue's own parser to report.
 */
function collectBindingPatternNames(directive: DirectiveNode | undefined): Set<string> {
    const scopeNames = new Set<string>();

    getBindingPatternSources(directive).forEach((expression) => {
        try {
            const { pattern } = parseBindingPattern(expression.content);
            addPatternNames(pattern, scopeNames);
        } catch {
            if (directive?.name === 'for') {
                scopeNames.add(expression.content);
            }
        }
    });

    return scopeNames;
}

/**
 * Where an expression's own offsets start inside the template, or null when they cannot be mapped.
 *
 * Vue hands back the *decoded* expression text, so a source that contained an HTML entity is shorter
 * than the range it came from and every offset inside it would be wrong. Comparing the two is the whole
 * check. A dynamic argument (`@[eventName]`) reports its brackets as part of the range but not as part
 * of the content, which is the one systematic difference that is not a decode.
 */
function getTemplateOffset(expression: TemplateExpression, isDynamicArgument = false): number | null {
    const raw = isDynamicArgument ? expression.loc.source.slice(1, -1) : expression.loc.source;

    if (raw !== expression.content) {
        return null;
    }

    return expression.loc.start.offset + (isDynamicArgument ? 1 : 0);
}

/**
 * Records the setup references of one expression, as names and as template-coordinate occurrences.
 *
 * An expression whose offsets cannot be mapped still contributes its names - the analyzer needs them to
 * decide forwarding, and to reject the file if one of them turns out to be forwarded.
 */
function addExpressionReferences(
    collector: ReferenceCollector,
    expression: TemplateExpression,
    templateScope: Set<string>,
    { isDynamicArgument = false, isBindingPattern = false } = {},
): void {
    const occurrences = isBindingPattern
        ? collectPatternOccurrences(expression.content, templateScope)
        : collectExpressionOccurrences(expression.content, templateScope);

    if (occurrences.length === 0) {
        return;
    }

    occurrences.forEach((occurrence) => collector.references.add(occurrence.name));

    const templateOffset = getTemplateOffset(expression, isDynamicArgument);

    if (templateOffset === null) {
        collector.unmappableExpressions.push({
            names: new Set(occurrences.map((occurrence) => occurrence.name)),
            offset: expression.loc.start.offset,
        });

        return;
    }

    occurrences.forEach((occurrence) => {
        collector.occurrences.push({
            name: occurrence.name,
            start: templateOffset + occurrence.start,
            end: templateOffset + occurrence.end,
            expansion: occurrence.expansion,
        });
    });
}

/**
 * Records the reference Vue's same-name binding shorthand (`:info`) reads through its argument.
 *
 * Vue camelizes the argument to find the binding, so `:aria-label` reads `ariaLabel`. An argument that
 * does not camelize into an identifier binds nothing a setup scope could provide and is left alone.
 *
 * The shorthand has no value range, so the occurrence is the empty span at the end of the directive:
 * a rewrite gives it the value it was standing in for.
 */
function addSameNameShorthandReference(
    collector: ReferenceCollector,
    directive: DirectiveNode,
    templateScope: Set<string>,
): void {
    const name = (directive.arg?.content ?? '').replace(/-(\w)/g, (_match, character: string) => character.toUpperCase());

    if (!/^[A-Za-z_$][A-Za-z0-9_$]*$/.test(name) || templateScope.has(name)) {
        return;
    }

    collector.references.add(name);
    collector.occurrences.push({
        name,
        start: directive.loc.end.offset,
        end: directive.loc.end.offset,
        expansion: 'same-name-shorthand',
    });
}

/**
 * Records the references of one directive: its dynamic argument, its expression, and its own patterns.
 *
 * `v-for` is the one directive whose expression is not a single JS expression, so it is taken apart
 * into its iterated source and its alias patterns; `v-slot` carries a binding pattern rather than an
 * expression. Everything else is a plain expression.
 */
function addDirectiveReferences(collector: ReferenceCollector, directive: DirectiveNode, templateScope: Set<string>): void {
    if (directive.arg && !directive.arg.isStatic) {
        addExpressionReferences(collector, directive.arg, templateScope, { isDynamicArgument: true });
    }

    if (directive.name === 'slot' || directive.name === 'for') {
        if (directive.name === 'for' && directive.forParseResult?.source) {
            addExpressionReferences(collector, directive.forParseResult.source, templateScope);
        }

        getBindingPatternSources(directive).forEach((pattern) =>
            addExpressionReferences(collector, pattern, templateScope, { isBindingPattern: true }),
        );

        return;
    }

    if (directive.exp?.content) {
        addExpressionReferences(collector, directive.exp, templateScope);
        return;
    }

    if (directive.name === 'bind' && directive.arg?.isStatic) {
        addSameNameShorthandReference(collector, directive, templateScope);
    }
}

/**
 * Checks whether a directive is the default slot shorthand/longhand.
 *
 */
function isDefaultSlotDirective(directive: DirectiveNode): boolean {
    return Boolean(
        directive.type === NodeTypes.DIRECTIVE &&
            directive.name === 'slot' &&
            (!directive.arg || (directive.arg.isStatic && directive.arg.content === 'default')),
    );
}

/**
 * Finds the default slot directive on an element.
 *
 */
function getDefaultSlotDirective(node: ElementNode): DirectiveNode | undefined {
    return node.props.find(
        (prop): prop is DirectiveNode => prop.type === NodeTypes.DIRECTIVE && isDefaultSlotDirective(prop as DirectiveNode),
    );
}

/**
 * Returns the static v-for directive on an element, when present.
 *
 */
function getForDirective(node: ElementNode): DirectiveNode | undefined {
    return node.props.find(
        (prop): prop is DirectiveNode => prop.type === NodeTypes.DIRECTIVE && (prop as DirectiveNode).name === 'for',
    );
}

/**
 * Collects references from descendants of a sw-block override slot.
 *
 */
function collectTemplateReferences(children: TemplateChildNode[], initialScope: Set<string>): TemplateReferences {
    const collector: ReferenceCollector = {
        references: new Set<string>(),
        occurrences: [],
        unmappableExpressions: [],
    };

    function visit(node: TemplateChildNode, scope: Set<string>): void {
        if (node.type === NodeTypes.INTERPOLATION) {
            addExpressionReferences(collector, node.content as TemplateExpression, scope);
            return;
        }

        if (node.type !== NodeTypes.ELEMENT) {
            return;
        }

        // Bound before the guard below: narrowing `node` through a type predicate leaves the element
        // branch with nothing left to be, and every property read after it degrades to `any`.
        const element = node as ElementNode;

        // A nested <sw-block extends> is its own extension point: the analyzer visits it separately and
        // gives it its own slot scope, so its content's references belong to that scope and not to this
        // one. Descending anyway would report every reference twice and rewrite the same range twice.
        if (isSwBlockExtends(node)) {
            return;
        }

        const forDirective = getForDirective(element);
        const childScope = new Set<string>(scope);
        const slotScopeNames = new Set<string>();

        if (forDirective) {
            addDirectiveReferences(collector, forDirective, scope);
            collectBindingPatternNames(forDirective).forEach((name) => childScope.add(name));
        }

        element.props.forEach((prop) => {
            if (prop.type !== NodeTypes.DIRECTIVE || prop === forDirective) {
                return;
            }

            const directive = prop as DirectiveNode;

            // Any slot directive - default, named (#item), or dynamic (#[name]) - is handled the same:
            // its binding-pattern references (destructuring defaults, computed keys) are forwarded, or
            // they resolve against the hidden override component and silently break; and its scope names
            // shadow the slot content, so a `#item="{ info }"` with a setup binding `info` does not
            // over-forward the shadowed `info`.
            addDirectiveReferences(collector, directive, childScope);

            if (directive.name === 'slot') {
                collectBindingPatternNames(directive).forEach((name) => slotScopeNames.add(name));
            }
        });

        const scopedChildrenScope = new Set<string>(childScope);
        slotScopeNames.forEach((name) => scopedChildrenScope.add(name));

        element.children.forEach((child) => visit(child, scopedChildrenScope));
    }

    children.forEach((child) => visit(child, new Set<string>(initialScope)));

    return collector;
}

/**
 * Checks whether an element is a `<sw-block>` carrying the given identity attribute.
 *
 * Accepts the static form (`name="x"`) and the bound form (`:name="x"`); the bound form is rejected
 * later by `assertSwBlockAttributes`, but has to be recognised here so it reaches that check.
 */
function isSwBlockWithIdentity(node: TemplateChildNode, attribute: 'name' | 'extends'): node is ElementNode {
    if (node.type !== NodeTypes.ELEMENT || node.tag !== 'sw-block') {
        return false;
    }

    return node.props.some((prop) => {
        if (prop.type === NodeTypes.ATTRIBUTE) {
            return prop.name === attribute;
        }

        const directive = prop as DirectiveNode;

        return directive.name === 'bind' && directive.arg?.isStatic && directive.arg.content === attribute;
    });
}

/**
 * Checks whether an element is an override block declaration (`<sw-block extends="...">`).
 */
function isSwBlockExtends(node: TemplateChildNode): node is ElementNode {
    return isSwBlockWithIdentity(node, 'extends');
}

/**
 * Checks whether an element is a base block declaration (`<sw-block name="...">`).
 */
function isSwBlockName(node: TemplateChildNode): node is ElementNode {
    return isSwBlockWithIdentity(node, 'name');
}

/**
 * Returns the static value of a `<sw-block>` identity attribute (`name` or `extends`), or null.
 *
 */
function getStaticSwBlockAttribute(node: ElementNode, attribute: 'name' | 'extends'): string | null {
    const identityAttribute = node.props.find(
        (prop): prop is Extract<ElementNode['props'][number], { type: NodeTypes.ATTRIBUTE }> =>
            prop.type === NodeTypes.ATTRIBUTE && prop.name === attribute,
    );

    return identityAttribute?.value?.content ?? null;
}

/**
 * Returns the static `name` of a base `<sw-block name="...">`, or null.
 */
function getStaticSwBlockName(node: ElementNode): string | null {
    return getStaticSwBlockAttribute(node, 'name');
}

/**
 * Returns the static `extends` of an override `<sw-block extends="...">`, or null.
 */
function getStaticSwBlockExtends(node: ElementNode): string | null {
    return getStaticSwBlockAttribute(node, 'extends');
}

/**
 * @private
 */
export {
    type DirectiveNode,
    type ElementNode,
    type TemplateOccurrence,
    type TemplateReferences,
    type UnmappableExpression,
    collectTemplateReferences,
    getDefaultSlotDirective,
    getStaticSwBlockExtends,
    getStaticSwBlockName,
    isSwBlockExtends,
    isSwBlockName,
};

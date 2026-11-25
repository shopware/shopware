/**
 * @sw-package framework
 *
 * The generator reads the structure of a template itself. These tests ask the Vue compiler the same questions
 * about the template the runtime factory builds: they put a probe into every block of every component of the
 * Administration, or replace the block, and compare what the compiler says with the snapshot.
 */
import { ErrorCodes, getBaseTransformPreset, NodeTypes, parse, transform } from '@vue/compiler-dom';
import type { CompilerError, DirectiveNode, ElementNode, RootNode, TemplateChildNode } from '@vue/compiler-dom';
import type { ComponentUnit } from '../../../model/model';
import { staticAttribute } from '../template-nodes';
import { runtimeComponents, vueTemplate } from './runtime-templates';
import type { RuntimeComponent } from './runtime-templates';

const TAG_ERRORS: number[] = [
    ErrorCodes.X_INVALID_END_TAG,
    ErrorCodes.X_MISSING_END_TAG,
];

const PROBE_TAG = 'sw-probe';

// A name no component declares, so Vue has to take it from the component instance.
const UNKNOWN_VARIABLE = 'swProbeUnknown';

const FROM_COMPONENT = '_ctx.';

interface Transformed {
    errors: CompilerError[];
    /** Every probe element with the node Vue put it into. */
    probes: { element: ElementNode }[];
}

/**
 * Runs the transforms of the compiler without generating code. Vue reports a template it cannot compile and
 * then fails in the code generation, which nothing here needs.
 */
function transformed(template: string): Transformed {
    const errors: CompilerError[] = [];
    const options = { onError: (error: CompilerError) => errors.push(error), onWarn: () => {} };
    const ast = parse(template, options);
    const [nodeTransforms, directiveTransforms] = getBaseTransformPreset(true);
    const probes: Transformed['probes'] = [];
    const collect = (node: TemplateChildNode | RootNode): void => {
        if (node.type === NodeTypes.ELEMENT && node.tag === PROBE_TAG) {
            probes.push({ element: node });
        }

        const children = node.type === NodeTypes.IF ? node.branches : 'children' in node ? node.children : [];

        children.forEach((child) => {
            if (typeof child === 'object') {
                collect(child as TemplateChildNode);
            }
        });
    };

    transform(ast, { ...options, prefixIdentifiers: true, nodeTransforms, directiveTransforms });
    collect(ast);

    return { errors, probes };
}

function count(errors: CompilerError[], codes: number[]): number {
    return errors.filter(({ code }) => codes.includes(Number(code))).length;
}

function unique(names: string[]): string[] {
    return [...new Set(names)].sort();
}

/** What Vue made of the bound attributes of a probe: `item` stays `item`, a name of the component gets `_ctx.`. */
function boundValues(element: ElementNode): string[] {
    return element.props
        .filter((prop): prop is DirectiveNode => prop.type === NodeTypes.DIRECTIVE && prop.name === 'bind')
        .map(({ exp }) => (exp?.type === NodeTypes.SIMPLE_EXPRESSION ? exp.content : ''));
}

/** The names of the blocks in the order the runtime template starts them. */
function blockOccurrences({ tokens }: RuntimeComponent): string[] {
    const names: string[] = [];

    vueTemplate(tokens, (name, content, occurrence) => {
        names[occurrence] = name;

        return content;
    });

    return names;
}

/** `inside(outer, inner)`: the block `inner` is `outer` or sits somewhere below it. */
function containment(unit: ComponentUnit): (outer: string, inner: string | null) => boolean {
    const parents = new Map<string, Set<string>>();

    unit.blocks.forEach(({ name, parent }) => {
        if (parent !== null) {
            parents.set(name, (parents.get(name) ?? new Set<string>()).add(parent));
        }
    });

    const inside = (outer: string, inner: string | null, seen: readonly string[] = []): boolean => {
        if (inner === null || seen.includes(inner)) {
            return false;
        }

        return (
            inner === outer ||
            [...(parents.get(inner) ?? [])].some((parent) =>
                inside(outer, parent, [
                    ...seen,
                    inner,
                ]),
            )
        );
    };

    return inside;
}

function withBlock(component: RuntimeComponent, block: string, replacement: (content: string) => string): string {
    return vueTemplate(component.tokens, (name, content) => (name === block ? replacement(content) : content));
}

/** Vue skips what is below an element it rejects, and a probe can break the `v-if` chain of its neighbours. */
function isCompiled(probe: ElementNode): boolean {
    return boundValues(probe)[0].startsWith(FROM_COMPONENT);
}

function blockOf(probe: ElementNode, occurrences: string[]): string {
    return occurrences[Number(staticAttribute(probe, 'occurrence'))];
}

/**
 * Whether an override of the block can start with `v-else-if`, wherever the template declares the block.
 * `null` when Vue did not get to see the block.
 */
function acceptsElseIf(component: RuntimeComponent, block: string): boolean | null {
    const probe = `<${PROBE_TAG} v-else-if="true" :probe="${UNKNOWN_VARIABLE}"></${PROBE_TAG}>`;
    const { probes, errors } = transformed(withBlock(component, block, (content) => `${probe}${content}`));
    const orphans = new Set(
        errors.filter(({ code }) => code === ErrorCodes.X_V_ELSE_NO_ADJACENT_IF).map(({ loc }) => loc?.start.offset),
    );
    const compiled = probes.map(({ element }) => element).filter(isCompiled);

    return compiled.length === 0 ? null : compiled.every(({ loc }) => !orphans.has(loc.start.offset));
}

/** `block: variable` for every variable of `candidates` that Vue resolves in every place of the block. */
function scopeVariables(component: RuntimeComponent, candidates: string[]): string[] {
    const occurrences = blockOccurrences(component);
    const attributes = [
        UNKNOWN_VARIABLE,
        ...candidates,
    ]
        .map((variable, index) => `:probe-${index}="${variable}"`)
        .join(' ');
    const { probes } = transformed(
        vueTemplate(
            component.tokens,
            (_name, content, occurrence) =>
                `<${PROBE_TAG} occurrence="${occurrence}" ${attributes}></${PROBE_TAG}>${content}`,
        ),
    );
    const outside = new Set<string>();
    const compiled = new Set<string>();

    probes
        .map(({ element }) => element)
        .filter(isCompiled)
        .forEach((probe) => {
            const block = blockOf(probe, occurrences);

            compiled.add(block);
            boundValues(probe)
                .slice(1)
                .filter((value) => value.startsWith(FROM_COMPONENT))
                .forEach((value) => outside.add(`${block}: ${value.slice(FROM_COMPONENT.length)}`));
        });

    return unique([...compiled].flatMap((name) => candidates.map((candidate) => `${name}: ${candidate}`))).filter(
        (variable) => !outside.has(variable),
    );
}

function tagErrors(template: string): number {
    const errors: CompilerError[] = [];

    parse(template, { onError: (error) => errors.push(error) });

    return count(errors, TAG_ERRORS);
}

function orphanedBranches(template: string): number {
    return count(transformed(template).errors, [ErrorCodes.X_V_ELSE_NO_ADJACENT_IF]);
}

describe('scripts/generate-public-api/read/template/block-structure against the Vue compiler', () => {
    const components = runtimeComponents();

    it('covers the templates of the Administration and every kind of structure', () => {
        const blocks = components.flatMap(({ unit }) => unit.blocks);
        const crossings = components.flatMap(({ unit }) => unit.crossings);

        expect(components.length).toBeGreaterThan(700);
        expect(blocks.filter(({ context }) => context.afterIf).length).toBeGreaterThan(500);
        expect(blocks.filter(({ context }) => context.scope.length > 0).length).toBeGreaterThan(1000);
        expect(crossings.filter(({ kind }) => kind === 'chain').length).toBeGreaterThan(100);
        expect(crossings.filter(({ kind }) => kind === 'element').length).toBeGreaterThan(3);
    });

    it('marks a block [after v-if] exactly where Vue accepts an override that starts with v-else-if', () => {
        const different = components
            .map((component) => {
                const { blocks } = component.unit;
                const accepted = new Map(
                    unique(blocks.map(({ name }) => name)).map((name) => [name, acceptsElseIf(component, name)]),
                );
                const compiled = [...accepted.keys()].filter((name) => accepted.get(name) !== null);

                return {
                    name: component.unit.name,
                    generated: compiled.filter((name) =>
                        blocks.filter((block) => block.name === name).every(({ context }) => context.afterIf),
                    ),
                    compiler: compiled.filter((name) => accepted.get(name)),
                };
            })
            .filter(({ generated, compiler }) => JSON.stringify(generated) !== JSON.stringify(compiler));

        expect(different).toEqual([]);
    });

    it('lists as the scope of a block exactly the variables Vue resolves there', () => {
        const different = components
            .map((component) => {
                const { blocks } = component.unit;
                const candidates = unique(blocks.flatMap(({ context }) => context.scope.map(({ name }) => name)));
                const everywhere = (name: string, variable: string): boolean =>
                    blocks
                        .filter((block) => block.name === name)
                        .every(({ context }) => context.scope.some((scoped) => scoped.name === variable));

                return {
                    name: component.unit.name,
                    generated: unique(
                        blocks.flatMap(({ name }) =>
                            candidates
                                .filter((variable) => everywhere(name, variable))
                                .map((variable) => `${name}: ${variable}`),
                        ),
                    ),
                    compiler: candidates.length === 0 ? [] : scopeVariables(component, candidates),
                };
            })
            .filter(({ generated, compiler }) => JSON.stringify(generated) !== JSON.stringify(compiler));

        expect(different).toEqual([]);
    });

    it('lists an element crossing exactly where emptying a block leaves a tag without its partner', () => {
        const different = components
            .map((component) => {
                const inside = containment(component.unit);
                const balanced = tagErrors(vueTemplate(component.tokens));
                const names = unique(component.unit.blocks.map(({ name }) => name));

                return {
                    name: component.unit.name,
                    generated: names.filter((name) =>
                        component.unit.crossings.some(
                            ({ kind, opens, closes }) => kind === 'element' && inside(name, opens) !== inside(name, closes),
                        ),
                    ),
                    compiler: names.filter((name) => tagErrors(withBlock(component, name, () => '')) !== balanced),
                };
            })
            .filter(({ generated, compiler }) => JSON.stringify(generated) !== JSON.stringify(compiler));

        expect(different).toEqual([]);
    });

    it('lists a v-if crossing exactly where replacing a block leaves a v-else without its v-if', () => {
        const different = components
            .map((component) => {
                const inside = containment(component.unit);
                const orphaned = orphanedBranches(vueTemplate(component.tokens));
                const balanced = tagErrors(vueTemplate(component.tokens));
                // An override that adds an element to a block without content breaks a chain that only passes it.
                const replaced = (name: string): string =>
                    withBlock(component, name, (content) => (content.trim() === '' ? content : '<i></i>'));
                // A block that takes one tag of an element along changes which elements are neighbours at all.
                const names = unique(component.unit.blocks.map(({ name }) => name)).filter(
                    (name) => tagErrors(replaced(name)) === balanced,
                );

                return {
                    name: component.unit.name,
                    generated: names.filter((name) =>
                        component.unit.crossings.some(
                            ({ kind, opens, closes }) => kind === 'chain' && inside(name, opens) && !inside(name, closes),
                        ),
                    ),
                    compiler: names.filter((name) => orphanedBranches(replaced(name)) > orphaned),
                };
            })
            .filter(({ generated, compiler }) => JSON.stringify(generated) !== JSON.stringify(compiler));

        expect(different).toEqual([]);
    });
});

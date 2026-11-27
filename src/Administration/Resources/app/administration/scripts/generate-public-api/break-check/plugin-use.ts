/**
 * @sw-package framework
 *
 * How plugin code reaches the members of a unit, in the words of a finding. A plugin overrides a method of a
 * component and calls the original through `$super`, while it calls a service or a store from outside. The
 * rules are the same for all of them, so each kind of unit brings the clauses that differ, and the code of a
 * plugin that uses one of its members.
 */
import type { PluginExample } from './finding';
import type { MemberKind } from '../model/members';

/**
 * @private
 *
 * The member a plugin uses in an example. `parameters` are those the source names for a method at the base
 * commit, `null` for every other member.
 */
export interface UsedMember {
    kind: MemberKind;
    name: string;
    parameters: string[] | null;
}

/**
 * @private
 *
 * The clauses of the findings about a method or a property of one unit. Each one completes a sentence such as
 * `The method is gone, so <clause>.`
 */
export interface PluginUse {
    /** How plugin code names the unit itself. */
    unit: string;
    /** What happens to plugin code once the unit is gone. */
    withoutUnit: string;
    withoutMethod: (method: string) => string;
    withoutProperty: (property: string) => string;
    /** Who gets a Promise from a method that returned a plain value before. */
    gettingPromise: (method: string) => string;
    /** Who chained on the Promise that a method no longer returns. */
    losingPromise: (method: string) => string;
    /** The calls that pass arguments to the method, as the subject of a clause in the plural. */
    calls: (method: string) => string;
    withoutParameter: (method: string) => string;
    /** What a new optional parameter means for existing plugin code, `null` when it means nothing. */
    newOptionalParameter: string | null;
    /** Plugin code that uses the unit as a whole, when plugin code names it. */
    unitExample?: PluginExample;
    /** Plugin code that uses the member, when there is a usual way to. */
    memberExample: (member: UsedMember) => PluginExample | undefined;
}

const INDENT = '    ';

function script(lines: string[], broken: number): PluginExample {
    return { language: 'javascript', lines, broken };
}

function template(lines: string[], broken: number): PluginExample {
    return { language: 'twig', lines, broken };
}

/**
 * @private
 *
 * A plugin template that overrides `block` without `{% parent %}`. The line that breaks is the block itself, or
 * its content when `brokenContent` is what no longer works there.
 */
export function blockOverride(block: string, brokenContent?: string): PluginExample {
    return template(
        [`{% block ${block} %}`, `${INDENT}${brokenContent ?? '<my-plugin-content />'}`, '{% endblock %}'],
        brokenContent === undefined ? 0 : 1,
    );
}

/** An override of the component with one member in `section`. `broken` is the index of the line in `member`. */
function componentOverride(
    component: string,
    section: 'computed' | 'methods',
    member: string[],
    broken: number,
): PluginExample {
    return script(
        [
            `Shopware.Component.override('${component}', {`,
            `${INDENT}${section}: {`,
            ...member.map((line) => `${INDENT}${INDENT}${line}`),
            `${INDENT}},`,
            '});',
        ],
        broken + 2,
    );
}

/** A method of a plugin override that reads `expression`, as the methods of the component itself do. */
function readInOverride(component: string, expression: string): PluginExample {
    return componentOverride(component, 'methods', ['onPluginAction() {', `${INDENT}return ${expression};`, '},'], 1);
}

function argumentsOf({ parameters }: UsedMember): string {
    return parameters?.join(', ') ?? '...args';
}

/** How a plugin uses each kind of member of a component. A mixin has members of its own, so it has no one way. */
const COMPONENT_EXAMPLES: Partial<Record<MemberKind, (component: string, member: UsedMember) => PluginExample>> = {
    inject: (component, { name }) => readInOverride(component, `this.${name}`),
    provide: (_component, { name }) =>
        script(["Shopware.Component.register('my-plugin-component', {", `${INDENT}inject: ['${name}'],`, '});'], 1),
    prop: (component, { name }) => template([`<${component} :${name}="value" />`], 0),
    emit: (component, { name }) => template([`<${component} @${name}="onPluginAction" />`], 0),
    slot: (component, { name }) =>
        template(
            [
                `<${component}>`,
                `${INDENT}<template #${name}>`,
                `${INDENT}${INDENT}<my-plugin-content />`,
                `${INDENT}</template>`,
                `</${component}>`,
            ],
            1,
        ),
    data: (component, { name }) => readInOverride(component, `this.${name}`),
    computed: (component, { name }) =>
        componentOverride(component, 'computed', [`${name}() {`, `${INDENT}return this.$super('${name}');`, '},'], 1),
    method: (component, member) =>
        componentOverride(
            component,
            'methods',
            [
                `${member.name}(${argumentsOf(member)}) {`,
                `${INDENT}return this.$super(${[`'${member.name}'`, argumentsOf(member)].filter(Boolean).join(', ')});`,
                '},',
            ],
            1,
        ),
    ref: (component, { name }) => readInOverride(component, `this.$refs.${name}`),
};

const COMPONENT_MEMBERS: Omit<PluginUse, 'unit' | 'withoutUnit' | 'memberExample'> = {
    withoutMethod: (method) => `plugin overrides that call $super('${method}') throw at runtime`,
    withoutProperty: (property) => `plugin overrides that read this.${property} get undefined`,
    gettingPromise: (method) => `plugin overrides that use the return value of $super('${method}') get a Promise instead`,
    losingPromise: (method) => `plugin overrides that chain a .then() on $super('${method}') fail`,
    calls: (method) => `plugin calls and $super('${method}', ...) calls`,
    withoutParameter: (method) => `plugin overrides of ${method} that read it get undefined`,
    newOptionalParameter: 'Plugin overrides that call $super with explicit arguments do not pass it on.',
};

/** @private */
export function componentUse(name: string): PluginUse {
    return {
        unit: name,
        withoutUnit: `plugin templates that render ${name} fail to resolve it`,
        ...COMPONENT_MEMBERS,
        unitExample: template([`<${name} />`], 0),
        memberExample: (member) => COMPONENT_EXAMPLES[member.kind]?.(name, member),
    };
}

const CALLED: readonly MemberKind[] = ['method', 'action'];

/** Plugin code calls a method of the object and reads every other member of it. */
function objectMemberExample(unit: string, member: UsedMember): PluginExample {
    return script(
        [
            CALLED.includes(member.kind)
                ? `${unit}.${member.name}(${argumentsOf(member)});`
                : `const value = ${unit}.${member.name};`,
        ],
        0,
    );
}

/** The wording for an object that plugin code gets from the accessor `unit` and calls from outside. */
function objectUse(unit: string): Omit<PluginUse, 'withoutUnit'> {
    const member = (memberName: string): string => `${unit}.${memberName}`;

    return {
        unit,
        withoutMethod: (method) => `plugin code that calls ${member(method)}() throws at runtime`,
        withoutProperty: (property) => `plugin code that reads ${member(property)} gets undefined`,
        gettingPromise: (method) => `plugin code that uses the return value of ${member(method)}() gets a Promise instead`,
        losingPromise: (method) => `plugin code that chains a .then() on ${member(method)}() fails`,
        calls: (method) => `plugin calls of ${member(method)}()`,
        withoutParameter: (method) => `the value that plugin calls of ${member(method)}() pass for it is ignored`,
        newOptionalParameter: null,
        unitExample: script([`const value = ${unit};`], 0),
        memberExample: (used) => objectMemberExample(unit, used),
    };
}

/** @private */
export function serviceUse(name: string): PluginUse {
    const use = objectUse(`Shopware.Service('${name}')`);

    return { ...use, withoutUnit: `plugin code that uses ${use.unit} gets undefined` };
}

/**
 * @private
 *
 * The wording for an object of the global `Shopware` object, which plugin code names by its path. The object
 * is gone as a unit while the object above it still holds it, when the snapshot can no longer read it.
 */
export function globalUse(path: string): PluginUse {
    return {
        ...objectUse(path),
        withoutUnit: `the snapshot no longer lists the members of ${path}, and plugin code that uses them can break unnoticed`,
    };
}

/** @private */
export function storeUse(name: string): PluginUse {
    const use = objectUse(`Shopware.Store.get('${name}')`);

    return { ...use, withoutUnit: `plugin code that calls ${use.unit} throws at runtime` };
}

/**
 * @private
 *
 * Plugin code reaches the members of a class through every service that is an instance of it. A class is never
 * reported as a whole, so `withoutUnit` only completes the type. Plugin code has no name for an instance, so
 * there is no code to show for it.
 */
export function classUse(name: string): PluginUse {
    const use = objectUse(`<instance of ${name}>`);

    return {
        ...use,
        withoutUnit: `plugin code that uses ${use.unit} breaks`,
        unitExample: undefined,
        memberExample: () => undefined,
    };
}

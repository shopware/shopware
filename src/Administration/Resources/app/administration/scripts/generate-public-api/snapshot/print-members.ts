/**
 * @sw-package framework
 *
 * Prints the sections of a report that list the members of a component, one line per member.
 */
import { byCodePoint } from '../helpers/code-point-order';
import { blockLabel } from '../model/blocks';
import type { FlagContext } from '../model/flag-states';
import { isKind, KINDS, MEMBER_KINDS, namePath, propType } from '../model/members';
import type { DefaultValue, Member, MemberKind, Parameter } from '../model/members';
import { flagStateMarker, line, markerLabels } from './report-outline';
import type { Line, Section } from './report-outline';

const UNTYPED = 'any';

/**
 * @private
 *
 * The kinds whose sections stand before or after the blocks of a report, in the order of the sections.
 */
export function kindsPlaced(placement: (typeof MEMBER_KINDS)[MemberKind]['placement']): MemberKind[] {
    return KINDS.filter((kind) => MEMBER_KINDS[kind].placement === placement);
}

/** The default as the source writes it, `factory` for a factory, `null` without one. */
function defaultText(value: DefaultValue): string | null {
    if (value.kind === 'none') {
        return null;
    }

    return value.kind === 'value' ? value.text : 'factory';
}

function parameterText({ name, type, default: value, optional, rest }: Parameter): string {
    const typeText = type ?? (rest ? `${UNTYPED}[]` : UNTYPED);
    const fallback = defaultText(value);

    return `${rest ? '...' : ''}${name}${optional ? '?' : ''}: ${typeText}${fallback === null ? '' : ` = ${fallback}`}`;
}

function propText(prop: Member<'prop'>): string {
    const fallback = defaultText(prop.default);

    return `${prop.name}${prop.required ? '' : '?'}: ${propType(prop) ?? UNTYPED}${fallback === null ? '' : `  default=${fallback}`}`;
}

/** A member without a signature in the source takes whatever it is called with. */
function parametersText(parameters: Parameter[] | null): string {
    return parameters === null ? `...args: ${UNTYPED}[]` : parameters.map(parameterText).join(', ');
}

function returnText({ returns, returnsPromise }: Member<'method'>): string {
    return returns ?? (returnsPromise ? `Promise<${UNTYPED}>` : UNTYPED);
}

/** A nested data key is printed below its parent, indented once per level, with its own name only. */
function dataText(member: Member<'data'>): string {
    const path = namePath(member);

    return `${'  '.repeat(path.length - 1)}${path.at(-1)}: ${member.nested ? 'object' : UNTYPED}`;
}

const MEMBER_TEXTS: { [Kind in MemberKind]: (member: Member<Kind>) => string } = {
    mixin: ({ name }) => name,
    inject: ({ name, from }) => (from === name ? name : `${name} from ${from}`),
    provide: ({ name }) => `${name}: ${UNTYPED}`,
    prop: propText,
    emit: ({ name, payload }) => `${name}(${parametersText(payload)})`,
    slot: ({ name, scope, block }) =>
        `${name}  ${scope.length === 0 ? '{}' : `{ ${scope.join(', ')} }`}  block=${blockLabel(block)}`,
    data: dataText,
    computed: ({ name, type }) => `${name}: ${type ?? UNTYPED}`,
    method: (method) => `${method.name}(${parametersText(method.parameters)}): ${returnText(method)}`,
    ref: ({ name, target, list, block }) => `${name}  -> ${target}${list ? '  list' : ''}  block=${blockLabel(block)}`,
};

/** By name, a nested data key directly below its parent. */
function inSectionOrder(a: Member, b: Member): number {
    const pathA = namePath(a);
    const pathB = namePath(b);
    const differs = pathA.findIndex((part, index) => part !== pathB[index]);

    return differs === -1 || differs >= pathB.length
        ? pathA.length - pathB.length
        : byCodePoint(pathA[differs], pathB[differs]);
}

function memberLine<Kind extends MemberKind>(member: Member<Kind>, flags: FlagContext): Line {
    const text = (MEMBER_TEXTS[member.kind] as (member: Member<Kind>) => string)(member);

    return line(
        `  ${text}`,
        flagStateMarker(member.states, flags),
        ...markerLabels(member.markers),
        member.isPrivate ? 'private' : null,
    );
}

/** The mixins of a component are one line, in the order the component applies them. */
function mixinLines(mixins: Member<'mixin'>[]): Line[] {
    return mixins.length === 0 ? [] : [line(`  ${mixins.map(({ name }) => name).join(', ')}`)];
}

/**
 * @private
 *
 * One section per kind of `kinds` that the component has members of, in that order.
 */
export function memberSections(members: Member[], kinds: readonly MemberKind[], flags: FlagContext): Section[] {
    return kinds.map((kind) => {
        const ofKind = members.filter(isKind(kind));

        return {
            heading: MEMBER_KINDS[kind].heading,
            lines:
                kind === 'mixin'
                    ? mixinLines(members.filter(isKind('mixin')))
                    : [...ofKind].sort(inSectionOrder).map((member) => memberLine(member, flags)),
        };
    });
}

/**
 * @sw-package framework
 *
 * The helpers that components spread into `computed` and `methods`, with the member names each call adds.
 * The names are known when the call lists them as literals.
 */
import camelCase from 'lodash/camelCase';
import ts from 'typescript';
import { memberName, withoutTypeSyntax } from '../script-nodes';

function listed(list: ts.Expression | undefined): string[] | null {
    const value = list && withoutTypeSyntax(list);

    if (value === undefined) {
        return null;
    }

    if (ts.isArrayLiteralExpression(value)) {
        const names = value.elements.filter(ts.isStringLiteralLike).map(({ text }) => text);

        return names.length === value.elements.length ? names : null;
    }

    if (!ts.isObjectLiteralExpression(value)) {
        return null;
    }

    const names = value.properties.map(memberName).filter((name) => name !== null);

    return names.length === value.properties.length ? names : null;
}

/** `mapPropertyErrors('product', ['name'])` adds `productNameError`, as `map-errors.service.ts` names it. */
function errorNames([subject, properties]: readonly ts.Expression[]): string[] | null {
    const names = listed(properties);

    return subject !== undefined && ts.isStringLiteralLike(subject) && names !== null
        ? names.map((property) => camelCase(`${subject.text}.${property}.error`))
        : null;
}

/** `mapState(useStore, ['items'])` and `mapState('namespace', { items: 'list' })` add the listed names. */
function mappedNames(callArguments: readonly ts.Expression[]): string[] | null {
    return listed(callArguments.at(-1));
}

const HELPERS: Record<string, (callArguments: readonly ts.Expression[]) => string[] | null> = {
    mapPropertyErrors: errorNames,
    mapCollectionPropertyErrors: errorNames,
    mapState: mappedNames,
    mapWritableState: mappedNames,
    mapGetters: mappedNames,
    mapActions: mappedNames,
    mapMutations: mappedNames,
};

/**
 * @private
 *
 * The member names that spreading `expression` adds, `null` when they cannot be read from the source.
 */
export function spreadNames(expression: ts.Expression, source: ts.SourceFile): string[] | null {
    const call = withoutTypeSyntax(expression);

    if (!ts.isCallExpression(call)) {
        return null;
    }

    const helper = call.expression.getText(source).split('.').at(-1) ?? '';

    return Object.hasOwn(HELPERS, helper) ? HELPERS[helper](call.arguments) : null;
}

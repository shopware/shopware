/**
 * Expands the statically analysable computed spreads the Administration uses
 * into explicit named entries.
 *
 * A spread hides its keys behind a helper call, so without this the whole entry
 * is a manual TODO — and every `this.<generatedName>` reading one of those keys
 * becomes an `unknown this property` drop on top. The helpers below build their
 * getters from literal arguments only, so the same getters can be written out
 * here; anything whose keys are not decidable from the source keeps the TODO.
 */
import type { ArrayLiteralExpression, CallExpression, Node as TsNode, SpreadAssignment } from 'ts-morph';
import { Node, SyntaxKind } from 'ts-morph';
import camelCase from 'lodash/camelCase';
import { quoteJsString } from '../string-literals';
import { buildPropertyAccess, isSafeIdentifier, sanitizeTodoCommentText } from './helpers';
import type { ComputedProp } from './types';

/**
 * Returns the entries a computed spread expands to, or null when the spread is
 * not one of the supported helpers or its arguments are not literals.
 * An empty array is a successful expansion of a helper called without
 * properties — it contributes no computed entry, exactly like the helper.
 *
 * `trustedHelperNames` is what makes the name mean the helper: it holds only the
 * names this module provably bound from `Shopware.Component.getComponentHelper()`,
 * `pinia`, or `map-errors.service`. Without it a `mapState` imported from `vuex`
 * — whose getters read `this.$store` — would be ported with Pinia semantics.
 */
export function expandComputedSpread(spread: SpreadAssignment, trustedHelperNames: Set<string>): ComputedProp[] | null {
    const call = spread.getExpression().asKind(SyntaxKind.CallExpression);
    // Only a bare, unconditionally called identifier is matched: the helpers are
    // destructured into a module local first, and `helper?.(…)` may not be a call
    // at all.
    const callee = call?.getExpression().asKind(SyntaxKind.Identifier)?.getText();

    if (!call || !callee || call.getQuestionDotTokenNode() || !trustedHelperNames.has(callee)) {
        return null;
    }

    switch (callee) {
        case 'mapPropertyErrors':
            return expandPropertyErrors(call, callee, 'entity');
        case 'mapCollectionPropertyErrors':
            return expandPropertyErrors(call, callee, 'collection');
        case 'mapState':
            return expandMapState(call, callee);
        default:
            // mapPageErrors takes cross-module config objects, and any other
            // helper is unknown — both keep the manual TODO.
            return null;
    }
}

/**
 * Ports `mapPropertyErrors` / `mapCollectionPropertyErrors` from
 * `src/app/service/map-errors.service.ts`. The generated getter reads
 * `this.<entity>` so the normal rewrite resolves it — and drops the entry with a
 * reason when the entity is not a migrated member, which is the honest outcome.
 */
function expandPropertyErrors(call: CallExpression, callee: string, shape: 'entity' | 'collection'): ComputedProp[] | null {
    const entityName = readStringLiteral(call.getArguments()[0]);
    const properties = readStringLiteralArray(call.getArguments()[1]);

    // The generated body reads `this.<entityName>`; a name that is not an
    // identifier would need element access, which the rewrite rejects anyway.
    if (entityName === null || properties === null || !isSafeIdentifier(entityName)) {
        return null;
    }

    return properties.map((property) => ({
        name: camelCase(`${entityName}.${property}.error`),
        kind: 'getter',
        comment: buildComment(callee, call),
        bodyText:
            shape === 'entity'
                ? [
                      `const entity = this.${entityName};`,
                      `const isEntity = entity && typeof entity.getEntityName === 'function';`,
                      `if (!isEntity) { return null; }`,
                      `return Shopware.Store.get('error').getApiError(entity, ${quoteJsString(property)});`,
                  ].join('\n')
                : [
                      `const entityCollection = this.${entityName};`,
                      `if (!Array.isArray(entityCollection)) { return null; }`,
                      `return entityCollection.map((entity) => Shopware.Store.get('error').getApiError(entity, ${quoteJsString(property)}));`,
                  ].join('\n'),
    }));
}

/**
 * Ports Pinia's `mapState(useStore, [keys])`, which builds one getter per key
 * that resolves the store and reads the key off it. The store expression is
 * re-evaluated per getter, exactly as Pinia does.
 */
function expandMapState(call: CallExpression, callee: string): ComputedProp[] | null {
    const keysArgument = call.getArguments()[1];
    const storeExpression = readStoreExpression(call.getArguments()[0]);
    const keys = keysArgument === undefined ? null : readStringLiteralArray(keysArgument);

    // The object form (`mapState(store, { alias: 'key' })`) renames keys and is
    // not covered here. A missing second argument is not an empty list either:
    // Pinia runs `Object.keys(keysOrMapper)` on it and throws.
    if (storeExpression === null || keys === null) {
        return null;
    }

    return keys.map((key) => ({
        name: key,
        kind: 'getter',
        comment: buildComment(callee, call),
        bodyText: `return ${buildPropertyAccess(storeExpression, key)};`,
    }));
}

/**
 * Pinia calls the first argument as `useStore(this.$pinia)`, so only the two
 * shapes that ignore or accept that argument can be re-emitted as a plain
 * expression. A parameterised arrow (`(state) => state.swProductDetail`) cannot:
 * its body reads a name that only exists inside the arrow, and copying the body
 * out would emit an unbound identifier that compiles and then throws.
 */
function readStoreExpression(argument: TsNode | undefined): string | null {
    if (!argument) {
        return null;
    }

    // Example: `mapState(() => Store.get('swFlow'), […])`
    const arrow = argument.asKind(SyntaxKind.ArrowFunction);
    if (arrow) {
        const body = arrow.getBody();

        // A block-bodied arrow can run statements before returning the store.
        return arrow.getParameters().length > 0 || Node.isBlock(body) ? null : body.getText();
    }

    // Example: `mapState(useShopwareServicesStore, […])`
    const useStore = argument.asKind(SyntaxKind.Identifier);

    return useStore ? `${useStore.getText()}()` : null;
}

function readStringLiteral(argument: TsNode | undefined): string | null {
    return argument?.asKind(SyntaxKind.StringLiteral)?.getLiteralValue() ?? null;
}

/**
 * A missing argument reads as `[]`, which is the `properties: K[] = []` default
 * of the two error helpers. `mapState` has no such default — its caller checks
 * for the missing argument before calling this.
 */
function readStringLiteralArray(argument: TsNode | undefined): string[] | null {
    if (!argument) {
        return [];
    }

    const array: ArrayLiteralExpression | undefined = argument.asKind(SyntaxKind.ArrayLiteralExpression);
    const elements = array?.getElements();

    if (!elements?.every((element) => element.isKind(SyntaxKind.StringLiteral))) {
        return null;
    }

    return elements.map((element) => element.asKindOrThrow(SyntaxKind.StringLiteral).getLiteralValue());
}

function buildComment(callee: string, call: CallExpression): string {
    const firstArgument = call.getArguments()[0];

    return `from the ...${callee}(${firstArgument ? sanitizeTodoCommentText(firstArgument.getText()) : ''}, …) computed spread`;
}

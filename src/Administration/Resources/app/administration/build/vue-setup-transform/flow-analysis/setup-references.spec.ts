/**
 * @sw-package framework
 */

import { parse, parseExpression } from '@babel/parser';
import { findLocalSetupReference, findLocalSetupTypeReference } from './setup-references';

/** Runs the value-reference finder over one parsed expression. */
function findValueReference(expressionSource: string, localBindings: string[]): string | null {
    const node = parseExpression(expressionSource, {
        plugins: [
            'typescript',
        ],
    });

    return findLocalSetupReference(node, new Set(localBindings))?.name ?? null;
}

/** Runs the type-reference finder over one parsed statement's program. */
function findTypeReference(source: string, localBindings: string[]): string | null {
    const program = parse(source, {
        sourceType: 'module',
        plugins: [
            'typescript',
        ],
    }).program;

    return findLocalSetupTypeReference(program, new Set(localBindings))?.name ?? null;
}

describe('build/vue-setup-transform/flow-analysis setup references', () => {
    describe('findLocalSetupReference (value positions)', () => {
        it('finds a read of a local binding', () => {
            expect(findValueReference('{ default: events }', ['events'])).toBe('events');
        });

        it('ignores a name shadowed by a function parameter', () => {
            expect(findValueReference('{ validator: (events) => events.length }', ['events'])).toBeNull();
        });

        it('ignores a name shadowed by a body-local declaration', () => {
            expect(findValueReference('{ default: () => { const events = []; return events; } }', ['events'])).toBeNull();
        });

        it('ignores a matching name used only as a static key or member property', () => {
            expect(findValueReference('{ events: 1 }', ['events'])).toBeNull();
            expect(findValueReference('source.events', ['events'])).toBeNull();
        });
    });

    describe('findLocalSetupTypeReference (type positions)', () => {
        it('finds a type reference to a local binding (e.g. an enum used as a type)', () => {
            expect(findTypeReference('const value: Kind = other;', ['Kind'])).toBe('Kind');
        });

        it('finds a typeof query of a local binding', () => {
            expect(findTypeReference('const value: typeof local = other;', ['local'])).toBe('local');
        });

        it('ignores a type-literal member key that matches a local binding name', () => {
            // `save` is a property name in the type, not a reference to the `save` value binding.
            expect(findTypeReference('type Shape = { save: number };', ['save'])).toBeNull();
        });
    });
});

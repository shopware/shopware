import { mapCollectionPropertyErrors, mapPropertyErrors } from '../../../../src/app/service/map-errors.service';
import { findComponentRegistration, parseSource } from './ast';
import { extractComputedProps } from './extract-computed';
import type { ComputedProp } from './types';

/**
 * The expanded `mapPropertyErrors` / `mapCollectionPropertyErrors` getters are a
 * hand-written port of `src/app/service/map-errors.service.ts`. A port can drift
 * from its original silently, so every case below runs the generated body and
 * the getter the real service builds against the same receiver and compares the
 * results — the service is imported, not described.
 */
function extractComputed(computedSource: string): ComputedProp[] {
    const sourceFile = parseSource(`Shopware.Component.register('sw-test', { computed: { ${computedSource} } });`);
    const optionsObject = findComponentRegistration(sourceFile)?.optionsObject;

    if (!optionsObject) {
        throw new Error('fixture did not parse into a component registration');
    }

    return extractComputedProps(
        optionsObject,
        new Set([
            'mapPropertyErrors',
            'mapCollectionPropertyErrors',
        ]),
    ).computedProps;
}

/**
 * Rebuilds the extracted body into the Options API function it was ported from,
 * so it can be called with the same `this` as the service getter. The `this.<x>`
 * accesses are what `rewriteThisInBody` resolves later in the real pipeline.
 */
function toOptionsApiGetter(prop: ComputedProp): (this: unknown) => unknown {
    if (prop.kind !== 'getter') {
        throw new Error(`expected a getter entry, got ${prop.kind}`);
    }

    // eslint-disable-next-line @typescript-eslint/no-implied-eval, no-new-func
    return new Function(`return function () {\n${prop.bodyText}\n};`)() as (this: unknown) => unknown;
}

function createEntity(id: string): { id: string; getEntityName: () => string } {
    return { id, getEntityName: () => 'product' };
}

/**
 * Without a seeded error every getter would answer `null` and the comparisons
 * below would hold for two unrelated implementations, so the real error store is
 * filled and one case asserts a non-null result.
 */
function seedApiError(id: string, property: string): void {
    Shopware.Store.get('error').addApiError({
        expression: `product.${id}.${property}`,
        error: { code: 'REQUIRED', detail: 'This value should not be blank.' },
    });
}

describe('scripts/codemods/sfc-migration/script-transformer/expand-computed-spread', () => {
    beforeAll(() => {
        seedApiError('with-error', 'name');
        seedApiError('first', 'quantity');
    });

    describe('mapPropertyErrors', () => {
        const generated = extractComputed("...mapPropertyErrors('product', ['name', 'stock'])");
        const service = mapPropertyErrors('product', [
            'name',
            'stock',
        ]);

        it('generates one entry per property under the names the service uses', () => {
            expect(generated.map((prop) => prop.name)).toEqual(Object.keys(service));
        });

        it('reads a real error out of the store, so the comparisons below are not null against null', () => {
            expect(toOptionsApiGetter(generated[0]).call({ product: createEntity('with-error') })).not.toBeNull();
        });

        it.each([
            [
                'an entity with an error on the property',
                { product: createEntity('with-error') },
            ],
            [
                'an entity without an error',
                { product: createEntity('no-error') },
            ],
            [
                'a null entity',
                { product: null },
            ],
            [
                'a plain object that is not an entity',
                { product: { name: 'not an entity' } },
            ],
        ])('matches the service getter for %s', (_case, vm) => {
            generated.forEach((prop) => {
                const serviceGetter = service[prop.name as keyof typeof service] as (this: unknown) => unknown;

                expect(toOptionsApiGetter(prop).call(vm)).toEqual(serviceGetter.call(vm));
            });
        });
    });

    describe('mapCollectionPropertyErrors', () => {
        const generated = extractComputed("...mapCollectionPropertyErrors('lineItems', ['quantity'])");
        const service = mapCollectionPropertyErrors('lineItems', ['quantity']);

        it('generates one entry per property under the names the service uses', () => {
            expect(generated.map((prop) => prop.name)).toEqual(Object.keys(service));
        });

        it('reads a real error out of the store, so the comparisons below are not null against null', () => {
            expect(toOptionsApiGetter(generated[0]).call({ lineItems: [createEntity('first')] })).not.toEqual([null]);
        });

        it.each([
            [
                'a collection of entities',
                {
                    lineItems: [
                        createEntity('first'),
                        createEntity('second'),
                    ],
                },
            ],
            [
                'an empty collection',
                { lineItems: [] },
            ],
            [
                'a value that is not an array',
                { lineItems: null },
            ],
        ])('matches the service getter for %s', (_case, vm) => {
            generated.forEach((prop) => {
                const serviceGetter = service[prop.name as keyof typeof service] as (this: unknown) => unknown;

                expect(toOptionsApiGetter(prop).call(vm)).toEqual(serviceGetter.call(vm));
            });
        });
    });
});

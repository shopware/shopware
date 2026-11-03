import {
    definePrivateComponent,
    definePublicComponent,
    type PrivateInstance,
    type PublicInstance,
    type PublicOverride,
} from '../index';

/**
 * @sw-package framework
 *
 * Checks the types of a declared component. `tsc` reads this file: each `@ts-expect-error` line must fail to
 * compile, every other line must compile.
 */

const SwExample = definePublicComponent({
    name: 'sw-example',
    load: () =>
        Promise.resolve({
            default: {
                props: { label: { type: String, required: true } },
                computed: {
                    title(): string {
                        return 'title';
                    },
                    secretTitle(): string {
                        return 'secret';
                    },
                },
                methods: {
                    save(force: boolean): boolean {
                        return force;
                    },
                    secret(): void {},
                },
            },
        }),
    public: {
        props: ['label'],
        computed: ['title'],
        methods: ['save'],
    },
});

describe('public-component types', () => {
    it('rejects a public name the component does not have', () => {
        // ACT
        const declaration = definePublicComponent({
            name: 'sw-example',
            load: () => Promise.resolve({ default: { methods: { save(): void {} } } }),
            // @ts-expect-error 'sav' is not a method of the component
            public: { methods: ['sav'] },
        });

        // ASSERT
        expect(declaration.name).toBe('sw-example');
    });

    it('lets a component that extends another one list what it inherits', () => {
        // ACT
        const declaration = definePublicComponent({
            name: 'sw-example-child',
            extends: 'sw-example',
            load: () => Promise.resolve({ default: { methods: { save(): void {} } } }),
            public: { methods: ['save', 'inheritedReset'] },
        });
        const base: 'sw-example' = declaration.extends;

        // ASSERT
        expect(base).toBe('sw-example');
    });

    it('leaves the names of a typed .vue component to the build check', () => {
        // ARRANGE
        type TypedSfc = new () => { label: string; $props: { label: string } };

        // ACT
        const declaration = definePublicComponent({
            name: 'sw-example',
            load: () => Promise.resolve({ default: {} as TypedSfc }),
            public: { props: ['label'], emits: ['save'], setup: ['count'] },
        });
        const label: PublicInstance<typeof declaration>['label'] = 'Label';

        // ASSERT
        expect(declaration.public?.props).toEqual(['label']);
        expect(label).toBe('Label');
    });

    it('types the public instance with the listed members only', () => {
        // ARRANGE
        const instance = {
            label: 'Label',
            title: 'Title',
            save: (force: boolean) => force,
        } satisfies PublicInstance<typeof SwExample>;

        // ACT
        const saved: boolean = instance.save(true);
        // @ts-expect-error secret() is private
        const secret: PublicInstance<typeof SwExample>['secret'] = undefined;

        // ASSERT
        expect(SwExample.public?.methods).toEqual(['save']);
        expect(saved).toBe(true);
        expect(secret).toBeUndefined();
    });

    it('rejects a name a private component does not have, also without a component name', () => {
        // ACT
        const declaration = definePrivateComponent({
            load: () => Promise.resolve({ default: { props: { service: { type: Object } } } }),
            // @ts-expect-error 'servce' is not a prop of the component
            public: { props: ['servce'] },
        });

        // ASSERT
        expect(declaration.name).toBeUndefined();
    });

    it('types the private instance with the listed members only', () => {
        // ARRANGE
        const SwPrivateExample = definePrivateComponent({
            name: 'sw-private-example',
            load: SwExample.load,
            public: { props: ['label'], methods: ['save'] },
        });

        // ACT
        const instance = { label: 'Label', save: (force: boolean) => force } satisfies PrivateInstance<
            typeof SwPrivateExample
        >;
        // @ts-expect-error title is not listed
        const title: PrivateInstance<typeof SwPrivateExample>['title'] = undefined;

        // ASSERT
        expect(SwPrivateExample.name).toBe('sw-private-example');
        expect(instance.save(true)).toBe(true);
        expect(title).toBeUndefined();
    });

    it('lets an override replace public members and add its own, but not replace private ones', () => {
        // ACT
        const allowed: PublicOverride<typeof SwExample> = {
            methods: {
                save(force: boolean): boolean {
                    this.$super('save', force);

                    return !force && this.title.length > 0;
                },
                saveTwice(): void {},
            },
        };
        const privateOverride: PublicOverride<typeof SwExample> = {
            methods: {
                // @ts-expect-error secret() is private
                secret(): void {},
            },
        };
        const privateSuper: PublicOverride<typeof SwExample> = {
            methods: {
                save(): boolean {
                    // @ts-expect-error $super() reaches public members only
                    this.$super('secret');

                    return true;
                },
            },
        };

        // ASSERT
        expect(Object.keys(allowed.methods ?? {})).toEqual(['save', 'saveTwice']);
        expect(privateOverride.methods).toBeDefined();
        expect(privateSuper.methods).toBeDefined();
    });
});

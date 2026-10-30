import { definePublicComponent, type PublicInstance, type PublicOverride } from '../index';

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

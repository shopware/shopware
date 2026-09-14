/** @sw-package framework */
import { hasNamedInheritance, resolveLegacyInheritance } from './resolve-legacy-inheritance';

describe('legacy Options inheritance resolution', () => {
    it('resolves names in nested mixins without mutating shared ancestors', async () => {
        const parent = Object.freeze({ methods: { inherited: () => 'parent' } });
        const source = { mixins: [{ extends: 'parent' }] };
        expect(hasNamedInheritance(source)).toBe(true);
        const load = jest.fn(() => Promise.resolve(parent));
        const result = await resolveLegacyInheritance(source, load);
        expect((result.mixins?.[0] as { extends: object }).extends).toEqual(parent);
        expect((result.mixins?.[0] as { extends: object }).extends).not.toBe(parent);
        expect(source.mixins[0].extends).toBe('parent');
        expect(load).toHaveBeenCalledWith('parent');
    });
    it('rejects named cycles and allows repeated shared mixins in independent branches', async () => {
        await expect(
            resolveLegacyInheritance({ extends: 'loop' }, () => Promise.resolve({ extends: 'loop' })),
        ).rejects.toThrow('Circular');
        const mixin = { methods: { same: () => true } };
        await expect(
            resolveLegacyInheritance(
                {
                    mixins: [
                        mixin,
                        mixin,
                    ],
                },
                () => Promise.resolve(false),
            ),
        ).resolves.toMatchObject({
            mixins: [
                mixin,
                mixin,
            ],
        });
    });
});

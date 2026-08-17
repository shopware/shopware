/**
 * @sw-package framework
 */

import withTwigOwnerDataScope, {
    resetTwigOwnerDataScopeWarnings,
} from 'src/app/component/structure/sw-block-override/sw-block/twig-owner-data-scope';

describe('app/component/structure/sw-block-override/sw-block/twig-owner-data-scope.ts', () => {
    beforeEach(() => {
        resetTwigOwnerDataScopeWarnings();
    });

    afterEach(() => {
        jest.restoreAllMocks();
    });

    it('passes through the data of the Twig component', () => {
        const scope = withTwigOwnerDataScope({ headline: 'from the component' }, 'sw_demo_block');

        expect(scope.headline).toBe('from the component');
    });

    it('resolves `this` of a getter against the real scope, not the proxy', () => {
        const scope = withTwigOwnerDataScope(
            {
                count: 2,
                get doubled(): number {
                    return this.count * 2;
                },
            },
            'sw_demo_block',
        );

        expect(scope.doubled).toBe(4);
    });

    it('survives the destructuring pattern a native-owner override generates', () => {
        jest.spyOn(console, 'warn').mockImplementation(() => {});
        const namespace = Symbol('sw-demo.override');
        const scope = withTwigOwnerDataScope({ headline: 'from the component' }, 'sw_demo_block') as Record<string, never>;

        // Exactly what the setup transform emits as the block's slot scope.
        const destructure = (): unknown => {
            const {
                __swOverride: {
                    [namespace]: { clickCount },
                },
            } = scope as never;

            return clickCount;
        };

        expect(destructure).not.toThrow();
        expect(destructure()).toBeUndefined();
    });

    it('warns once per block about the state that cannot arrive', () => {
        const warn = jest.spyOn(console, 'warn').mockImplementation(() => {});
        const scope = withTwigOwnerDataScope({ headline: 'from the component' }, 'sw_demo_block') as Record<string, never>;

        // eslint-disable-next-line @typescript-eslint/no-unused-expressions
        scope.__swOverride;
        // eslint-disable-next-line @typescript-eslint/no-unused-expressions
        scope.__swOverride;

        expect(warn).toHaveBeenCalledTimes(1);
        expect(warn).toHaveBeenCalledWith(expect.stringContaining('sw_demo_block'));
        expect(warn).toHaveBeenCalledWith(expect.stringContaining('owned by a Twig component'));
    });

    it('leaves a real override state untouched', () => {
        const warn = jest.spyOn(console, 'warn').mockImplementation(() => {});
        const overrideState = { real: true };
        const scope = withTwigOwnerDataScope({ __swOverride: overrideState }, 'sw_demo_block');

        expect(scope.__swOverride).toBe(overrideState);
        expect(warn).not.toHaveBeenCalled();
    });

    it('returns a nullish scope unchanged', () => {
        expect(withTwigOwnerDataScope(null, 'sw_demo_block')).toBeNull();
    });
});

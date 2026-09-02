/**
 * @sw-package framework
 */

import { mount } from '@vue/test-utils';
import { createActiveFeatureFlagsTest, createDeprecatedTest, createInactiveFeatureFlagsTest } from './jest-extensions';

const pendingFeatureFlagsSymbol = Symbol.for('shopware.pendingActiveFeatureFlags');
const pendingInactiveFeatureFlagsSymbol = Symbol.for('shopware.pendingInactiveFeatureFlags');

const defaultActiveFeatureFlags =
    (Reflect.get(globalThis, Symbol.for('shopware.defaultActiveFeatureFlags')) as string[] | undefined) ?? [];

function readPendingFeatureFlags(): string[] {
    const featureFlags: unknown = Reflect.get(globalThis, pendingFeatureFlagsSymbol);

    return Array.isArray(featureFlags)
        ? featureFlags.filter((featureFlag): featureFlag is string => typeof featureFlag === 'string')
        : [];
}

function createTestFunctionSpy() {
    // `it.skip` carries `each` in real Jest, so the spy has to as well.
    return Object.assign(jest.fn(), {
        skip: Object.assign(jest.fn(), { each: jest.fn(() => jest.fn()) }),
        each: jest.fn(() => jest.fn()),
    }) as unknown as jest.It;
}

describe('Jest feature flag extensions', () => {
    it('registers a deprecated test when its major feature flag is inactive', () => {
        const testFunction = createTestFunctionSpy();

        createDeprecatedTest(testFunction)('v99.0.0.0')('deprecated test', jest.fn());

        expect(testFunction).toHaveBeenCalledWith('deprecated test (removed in v99.0.0.0)', expect.any(Function), undefined);
        expect(testFunction.skip).not.toHaveBeenCalled();
    });

    it.activeFeatureFlags(['v6.8.0.0'])('skips a deprecated test when its major feature flag is active', () => {
        const testFunction = createTestFunctionSpy();

        createDeprecatedTest(testFunction)('v6.8.0.0')('deprecated test', jest.fn());

        expect(testFunction).not.toHaveBeenCalled();
        expect(testFunction.skip).toHaveBeenCalledWith(
            'deprecated test (removed in v6.8.0.0)',
            expect.any(Function),
            undefined,
        );
    });

    it('keeps the removal version unshortened in the suffix', () => {
        const testFunction = createTestFunctionSpy();

        // A synthetic future version keeps this independent of the runner's baseline flags.
        createDeprecatedTest(testFunction)('v99.0.0.0')('deprecated test', jest.fn());

        // The suffix has to stay greppable against the flag literal and the deprecation markers.
        expect(testFunction).toHaveBeenCalledWith(
            expect.stringContaining('(removed in v99.0.0.0)'),
            expect.any(Function),
            undefined,
        );
    });

    it('suffixes the whole title so Jest still interpolates table rows', () => {
        const testFunction = createTestFunctionSpy();
        const eachRegister = jest.fn();
        (testFunction.each as unknown as jest.Mock).mockReturnValue(eachRegister);

        createDeprecatedTest(testFunction)('v99.0.0.0').each([['first']])('handles %s', jest.fn());

        // Guards against producing 'handles (removed in v99.0.0.0) %s'.
        expect(eachRegister).toHaveBeenCalledWith('handles %s (removed in v99.0.0.0)', expect.any(Function), undefined);
    });

    it('forwards the tagged-template each values to Jest', () => {
        const testFunction = createTestFunctionSpy();
        const eachRegister = jest.fn();
        (testFunction.each as unknown as jest.Mock).mockReturnValue(eachRegister);

        // The tagged-template form calls each(strings, ...values); every interpolated value must survive.
        createDeprecatedTest(testFunction)('v99.0.0.0').each`col ${1} ${2}`('handles it', jest.fn());

        expect(testFunction.each).toHaveBeenCalledWith(
            expect.arrayContaining([
                'col ',
                ' ',
                '',
            ]),
            1,
            2,
        );
    });

    it.activeFeatureFlags(['v6.8.0.0'])('forwards each() to skip when the major feature flag is active', () => {
        const testFunction = createTestFunctionSpy();

        createDeprecatedTest(testFunction)('v6.8.0.0').each([['first']])('handles %s', jest.fn());

        expect(testFunction.skip.each).toHaveBeenCalledWith([['first']]);
        expect(testFunction.each).not.toHaveBeenCalled();
    });

    describe('active feature flag lifecycle', () => {
        let featureFlagsInSetup: string[] = [];

        beforeEach(() => {
            featureFlagsInSetup = [...globalThis.activeFeatureFlags];
        });

        afterEach(() => {
            // eslint-disable-next-line jest/no-standalone-expect -- Verifies the flags remain active after the test callback.
            expect(globalThis.activeFeatureFlags).toEqual([
                ...defaultActiveFeatureFlags,
                'EXISTING_FEATURE',
                'NEW_FEATURE',
            ]);
        });

        afterAll(() => {
            // eslint-disable-next-line jest/no-standalone-expect -- Verifies the environment restores the flags after teardown.
            expect(globalThis.activeFeatureFlags).toEqual(defaultActiveFeatureFlags);
        });

        it.activeFeatureFlags([
            'EXISTING_FEATURE',
            'NEW_FEATURE',
        ])('activates feature flags during setup, the test, and teardown', () => {
            expect(globalThis.activeFeatureFlags).toEqual([
                ...defaultActiveFeatureFlags,
                'EXISTING_FEATURE',
                'NEW_FEATURE',
            ]);

            expect(featureFlagsInSetup).toEqual([
                ...defaultActiveFeatureFlags,
                'EXISTING_FEATURE',
                'NEW_FEATURE',
            ]);
        });
    });

    it('publishes feature flags while the test registers', () => {
        let registeredFeatureFlags: string[] = [];
        const testFunction = jest.fn(() => {
            registeredFeatureFlags = readPendingFeatureFlags();
        }) as unknown as jest.It;
        const callback = jest.fn();

        createActiveFeatureFlagsTest(testFunction)(['NEW_FEATURE'])('feature test', callback);

        expect(testFunction).toHaveBeenCalledWith('feature test', callback, undefined);
        expect(registeredFeatureFlags).toEqual(['NEW_FEATURE']);
        // The slot must not outlive the registration, or the next plain it() would inherit the flags.
        expect(Reflect.has(globalThis, pendingFeatureFlagsSymbol)).toBeFalsy();
    });

    it('normalizes feature flags registered for a test', () => {
        let registeredFeatureFlags: string[] = [];
        const testFunction = jest.fn(() => {
            registeredFeatureFlags = readPendingFeatureFlags();
        }) as unknown as jest.It;

        createActiveFeatureFlagsTest(testFunction)(['v6.8.0.0'])('feature test', jest.fn());

        expect(registeredFeatureFlags).toEqual(['V6_8_0_0']);
    });

    it('publishes inactive feature flags for a test', () => {
        let registeredFeatureFlags: string[] = [];
        const testFunction = jest.fn(() => {
            registeredFeatureFlags = Reflect.get(globalThis, pendingInactiveFeatureFlagsSymbol) as string[];
        }) as unknown as jest.It;

        createInactiveFeatureFlagsTest(testFunction)(['v6.8.0.0'])('feature test', jest.fn());

        expect(testFunction).toHaveBeenCalledWith('feature test', expect.any(Function), undefined);
        expect(registeredFeatureFlags).toEqual(['V6_8_0_0']);
        expect(Reflect.has(globalThis, pendingInactiveFeatureFlagsSymbol)).toBeFalsy();
    });

    it('publishes feature flags for every row of a table', () => {
        let registeredFeatureFlags: string[] = [];
        const eachRegister = jest.fn(() => {
            registeredFeatureFlags = readPendingFeatureFlags();
        });
        const testFunction = Object.assign(jest.fn(), {
            skip: jest.fn(),
            each: jest.fn(() => eachRegister),
        }) as unknown as jest.It;

        createActiveFeatureFlagsTest(testFunction)(['v6.8.0.0']).each([
            ['first'],
            ['second'],
        ])('handles %s', jest.fn());

        expect(testFunction.each).toHaveBeenCalledWith([
            ['first'],
            ['second'],
        ]);
        expect(registeredFeatureFlags).toEqual(['V6_8_0_0']);
        expect(Reflect.has(globalThis, pendingFeatureFlagsSymbol)).toBeFalsy();
    });

    describe('feature flags reach their consumers', () => {
        it.activeFeatureFlags(['v6.8.0.0'])('is visible to Shopware.Feature', () => {
            expect(Shopware.Feature.isActive('v6.8.0.0')).toBe(true);
        });

        // @deprecated tag:v6.8.0 - Asserts the pre-major baseline, so it cannot run once v6.8 is the default.
        it.deprecated('v6.8.0.0')('is not visible to Shopware.Feature without the helper', () => {
            expect(Shopware.Feature.isActive('v6.8.0.0')).toBe(false);
        });

        it.activeFeatureFlags(['v6.8.0.0'])('normalizes across the boundary', () => {
            expect(Shopware.Feature.isActive('V6_8_0_0')).toBe(true);
        });

        it.activeFeatureFlags(['V6_8_0_0'])('normalizes the dotted lookup too', () => {
            expect(Shopware.Feature.isActive('v6.8.0.0')).toBe(true);
        });

        it.activeFeatureFlags(['v6.8.0.0'])('is visible to an injected feature service', () => {
            const wrapper = mount({
                inject: ['feature'],
                template: '<div>{{ feature.isActive("v6.8.0.0") }}</div>',
            });

            expect(wrapper.text()).toBe('true');
        });

        it.activeFeatureFlags(['v6.8.0.0']).each([
            ['first'],
            ['second'],
        ])('stays active for table row %s', () => {
            expect(Shopware.Feature.isActive('v6.8.0.0')).toBe(true);
        });

        it.activeFeatureFlags(['v6.8.0.0'])('throws when a local feature mock shadows the global one', () => {
            expect(() =>
                mount(
                    {
                        inject: ['feature'],
                        template: '<div />',
                    },
                    {
                        global: {
                            provide: {
                                feature: { isActive: () => false },
                            },
                        },
                    },
                ),
            ).toThrow(/shadows the global feature service/);
        });
    });
});

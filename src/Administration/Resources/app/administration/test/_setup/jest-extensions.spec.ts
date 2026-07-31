/**
 * @sw-package framework
 */

import { createActiveFeatureFlagsTest, createDeprecatedTest } from './jest-extensions';

describe('Jest feature flag extensions', () => {
    it('registers a deprecated test when its major feature flag is inactive', () => {
        const testFunction = Object.assign(jest.fn(), { skip: jest.fn() }) as unknown as jest.It;

        createDeprecatedTest(testFunction)('v6.8.0.0')('deprecated test', jest.fn());

        expect(testFunction).toHaveBeenCalledWith('deprecated test', expect.any(Function));
        expect(testFunction.skip).not.toHaveBeenCalled();
    });

    it('skips a deprecated test when its major feature flag is active', () => {
        globalThis.activeFeatureFlags = ['v6.8.0.0'];
        const testFunction = Object.assign(jest.fn(), { skip: jest.fn() }) as unknown as jest.It;

        createDeprecatedTest(testFunction)('v6.8.0.0')('deprecated test', jest.fn());

        expect(testFunction).not.toHaveBeenCalled();
        expect(testFunction.skip).toHaveBeenCalledWith('deprecated test', expect.any(Function));
    });

    it.activeFeatureFlags([
        'EXISTING_FEATURE',
        'NEW_FEATURE',
    ])('activates feature flags during a test', () => {
        // eslint-disable-next-line jest/no-standalone-expect -- The custom test helper is not known to eslint-plugin-jest.
        expect(globalThis.activeFeatureFlags).toEqual([
            'EXISTING_FEATURE',
            'NEW_FEATURE',
        ]);
    });

    it('restores feature flags after a test', () => {
        expect(globalThis.activeFeatureFlags).toEqual([]);
    });

    it('restores feature flags when an asynchronous test fails', async () => {
        globalThis.activeFeatureFlags = ['EXISTING_FEATURE'];
        const testFunction = jest.fn((name: string, callback?: jest.ProvidesCallback) => {
            return (callback as (() => PromiseLike<unknown>) | undefined)?.();
        }) as unknown as jest.It;
        const expectedError = new Error('Test failed');

        await expect(
            createActiveFeatureFlagsTest(testFunction)(['NEW_FEATURE'])('failing test', () => {
                expect(globalThis.activeFeatureFlags).toEqual([
                    'EXISTING_FEATURE',
                    'NEW_FEATURE',
                ]);

                return Promise.reject(expectedError);
            }),
        ).rejects.toBe(expectedError);

        expect(globalThis.activeFeatureFlags).toEqual(['EXISTING_FEATURE']);
    });
});

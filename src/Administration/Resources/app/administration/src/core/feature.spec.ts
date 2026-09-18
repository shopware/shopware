/**
 * @sw-package framework
 */

import Feature from './feature';

describe('core/feature', () => {
    const originalNodeEnv = process.env.NODE_ENV;

    beforeEach(() => {
        Feature.flags = {};
        process.env.NODE_ENV = 'test';
    });

    afterEach(() => {
        jest.restoreAllMocks();
    });

    afterAll(() => {
        process.env.NODE_ENV = originalNodeEnv;
    });

    describe('triggerDeprecationOrThrow', () => {
        it('warns when the major feature flag is inactive', () => {
            const consoleWarnSpy = jest.spyOn(console, 'warn').mockImplementation();

            Feature.triggerDeprecationOrThrow('V6_9_0_0', 'Use replacement() instead.');

            expect(consoleWarnSpy).toHaveBeenCalledWith('[Shopware Deprecation] Use replacement() instead.');
        });

        it('does not warn in production when the major feature flag is inactive', () => {
            process.env.NODE_ENV = 'production';
            const consoleWarnSpy = jest.spyOn(console, 'warn').mockImplementation();

            Feature.triggerDeprecationOrThrow('V6_9_0_0', 'Use replacement() instead.');

            expect(consoleWarnSpy).not.toHaveBeenCalled();
        });

        it('throws when the major feature flag is active', () => {
            Feature.init({ V6_9_0_0: true });

            expect(() => {
                Feature.triggerDeprecationOrThrow('V6_9_0_0', 'Use replacement() instead.');
            }).toThrow('Tried to access deprecated functionality: Use replacement() instead.');
        });
    });
});

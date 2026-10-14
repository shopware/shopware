/**
 * @sw-package framework
 */

import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { parse } from 'yaml';

import Feature, { UNREGISTERED_UPCOMING_MAJORS } from 'src/core/feature';
import normalizeFeatureFlag from 'test/_helper_/normalizeFeatureFlag';

describe('src/core/feature', () => {
    const originalFlags = { ...Feature.flags };

    beforeEach(() => {
        Feature.init({ V7_0_0_0: false });
    });

    afterEach(() => {
        Feature.flags = { ...originalFlags };
        jest.restoreAllMocks();
    });

    describe('triggerDeprecationOrThrow', () => {
        it('throws once the major flag is active', () => {
            Feature.init({ V7_0_0_0: true });

            expect(() => Feature.triggerDeprecationOrThrow('V7_0_0_0', 'oldMethod() is deprecated.')).toThrow(
                'Tried to access deprecated functionality: oldMethod() is deprecated.',
            );
        });

        it('logs an error instead of the warning for a flag that is not registered', () => {
            const warn = jest.spyOn(console, 'warn').mockImplementation(() => {});
            const error = jest.spyOn(console, 'error').mockImplementation(() => {});

            Feature.triggerDeprecationOrThrow('V6_8_0', 'typoMethod() is deprecated.');

            expect(warn).not.toHaveBeenCalled();
            expect(error).toHaveBeenCalledWith(
                '[Deprecation]',
                'typoMethod() is deprecated.\nNote: this deprecation has a typo: "V6_8_0" is an unknown feature flag. Please fix it.',
            );
        });

        it('warns instead of the error for an upcoming major the backend does not register yet', () => {
            const warn = jest.spyOn(console, 'warn').mockImplementation(() => {});
            const error = jest.spyOn(console, 'error').mockImplementation(() => {});

            Feature.triggerDeprecationOrThrow('V6_9_0_0', 'upcomingMethod() is deprecated.');

            expect(error).not.toHaveBeenCalled();
            expect(warn).toHaveBeenCalledWith('[Deprecation]', 'upcomingMethod() is deprecated.');
        });

        it('lists only upcoming majors the backend does not register yet', () => {
            const featureConfig = parse(
                readFileSync(
                    resolve(process.env.ADMIN_PATH!, '../../../../Core/Framework/Resources/config/packages/feature.yaml'),
                    'utf8',
                ),
            ) as { shopware: { feature: { flags: Array<{ name: string }> } } };
            const registeredFlags = featureConfig.shopware.feature.flags.map(({ name }) => normalizeFeatureFlag(name));

            expect(registeredFlags).toContain('V6_8_0_0');
            expect(UNREGISTERED_UPCOMING_MAJORS.filter((flag) => registeredFlags.includes(flag))).toEqual([]);
        });

        it('warns once per message while the major flag is inactive', () => {
            const warn = jest.spyOn(console, 'warn').mockImplementation(() => {});

            Feature.triggerDeprecationOrThrow('V7_0_0_0', 'otherMethod() is deprecated.');
            Feature.triggerDeprecationOrThrow('V7_0_0_0', 'otherMethod() is deprecated.');

            expect(warn).toHaveBeenCalledTimes(1);
            expect(warn).toHaveBeenCalledWith('[Deprecation]', 'otherMethod() is deprecated.');
        });

        it('warns for each distinct message while the major flag is inactive', () => {
            const warn = jest.spyOn(console, 'warn').mockImplementation(() => {});

            Feature.triggerDeprecationOrThrow('V7_0_0_0', 'firstMethod() is deprecated.');
            Feature.triggerDeprecationOrThrow('V7_0_0_0', 'secondMethod() is deprecated.');

            expect(warn.mock.calls).toEqual([
                ['[Deprecation]', 'firstMethod() is deprecated.'],
                ['[Deprecation]', 'secondMethod() is deprecated.'],
            ]);
        });
    });
});

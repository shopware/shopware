/**
 * @sw-package framework
 */

import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { parse } from 'yaml';

import Feature, { deprecationBaselineKey, UNREGISTERED_UPCOMING_MAJORS } from 'src/core/feature';
import deprecationBaseline from 'src/core/deprecation-baseline.json';
import normalizeFeatureFlag from 'test/_helper_/normalizeFeatureFlag';
import { useProductionDeprecationGuard } from 'test/_helper_/deprecations';

const listedBaselineKeys: string[] = [];

/**
 * Lists `key` in the deprecation baseline that `Feature.triggerDeprecationOrThrow` reads, until the test ends.
 * Use it for runtime cases; the Jest baseline helper only changes the Jest-side copy.
 *
 * @example
 * listInDeprecationBaseline('oldMethod() is deprecated.');
 */
function listInDeprecationBaseline(key: string): void {
    (deprecationBaseline as Record<string, unknown>)[key] = { 'sw-page': ['default', 'major'] };
    listedBaselineKeys.push(key);
}

describe('src/core/feature', () => {
    const originalFlags = { ...Feature.flags };

    beforeEach(() => {
        useProductionDeprecationGuard();
        Feature.init({ V7_0_0_0: false });
    });

    afterEach(() => {
        listedBaselineKeys.splice(0).forEach((key) => {
            delete (deprecationBaseline as Record<string, unknown>)[key];
        });
        Feature.flags = { ...originalFlags };
        jest.restoreAllMocks();
    });

    describe('triggerDeprecationOrThrow', () => {
        it('throws once the major flag is active for a message the deprecation baseline does not list', () => {
            Feature.init({ V7_0_0_0: true });
            listInDeprecationBaseline('otherMethod() is deprecated.');

            expect(() => Feature.triggerDeprecationOrThrow('V7_0_0_0', 'oldMethod() is deprecated.')).toThrow(
                'Tried to access deprecated functionality: oldMethod() is deprecated.',
            );
        });

        it('logs an error instead of throwing for a message in the deprecation baseline', () => {
            const error = jest.spyOn(console, 'error').mockImplementation(() => {});
            Feature.init({ V7_0_0_0: true });
            listInDeprecationBaseline('baselinedMethod() is deprecated.');

            const message = 'baselinedMethod() is deprecated.\nUsed in: sw-page > sw-example';

            expect(() => Feature.triggerDeprecationOrThrow('V7_0_0_0', message)).not.toThrow();
            expect(error).toHaveBeenCalledWith(
                '[Deprecation]',
                `${message}\nThis deprecation is in the core deprecation baseline, so it does not throw yet. Migrate your usage before the major: it will throw then.`,
            );
        });

        it('warns for a message in the deprecation baseline while the major flag is inactive', () => {
            const warn = jest.spyOn(console, 'warn').mockImplementation(() => {});
            const error = jest.spyOn(console, 'error').mockImplementation(() => {});
            listInDeprecationBaseline('inactiveBaselinedMethod() is deprecated.');

            Feature.triggerDeprecationOrThrow('V7_0_0_0', 'inactiveBaselinedMethod() is deprecated.');

            expect(error).not.toHaveBeenCalled();
            expect(warn).toHaveBeenCalledWith('[Deprecation]', 'inactiveBaselinedMethod() is deprecated.');
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

        it('warns once per message, whoever it blames', () => {
            const warn = jest.spyOn(console, 'warn').mockImplementation(() => {});

            Feature.triggerDeprecationOrThrow('V7_0_0_0', 'blamedMethod() is deprecated.', { _blame: 'author' });
            Feature.triggerDeprecationOrThrow('V7_0_0_0', 'blamedMethod() is deprecated.');

            expect(warn.mock.calls).toEqual([['[Deprecation]', 'blamedMethod() is deprecated.']]);
        });

        it('throws once the major flag is active, whoever it blames', () => {
            Feature.init({ V7_0_0_0: true });

            expect(() =>
                Feature.triggerDeprecationOrThrow('V7_0_0_0', 'blamedMethod() is deprecated.', { _blame: 'author' }),
            ).toThrow('Tried to access deprecated functionality: blamedMethod() is deprecated.');
        });
    });

    describe('branch', () => {
        it('runs beforeMajor while the major flag is inactive and returns its result', () => {
            const sinceMajor = jest.fn();

            expect(Feature.branch('V7_0_0_0', { beforeMajor: () => 'legacy', sinceMajor })).toBe('legacy');
            expect(sinceMajor).not.toHaveBeenCalled();
        });

        it('runs sinceMajor once the major flag is active and returns its result', () => {
            Feature.init({ V7_0_0_0: true });
            const beforeMajor = jest.fn();

            expect(Feature.branch('V7_0_0_0', { beforeMajor, sinceMajor: () => 'current' })).toBe('current');
            expect(beforeMajor).not.toHaveBeenCalled();
        });

        it('runs nothing once the major flag is active and only beforeMajor is given', () => {
            Feature.init({ V7_0_0_0: true });
            const beforeMajor = jest.fn();

            expect(Feature.branch('V7_0_0_0', { beforeMajor })).toBeUndefined();
            expect(beforeMajor).not.toHaveBeenCalled();
        });

        it('silences the deprecations of its major inside beforeMajor', () => {
            const warn = jest.spyOn(console, 'warn').mockImplementation(() => {});

            Feature.branch('v7.0.0.0', {
                beforeMajor: () => Feature.triggerDeprecationOrThrow('V7_0_0_0', 'silencedMethod() is deprecated.'),
            });
            Feature.triggerDeprecationOrThrow('V7_0_0_0', 'silencedMethod() is deprecated.');

            expect(warn.mock.calls).toEqual([['[Deprecation]', 'silencedMethod() is deprecated.']]);
        });

        it('keeps the deprecations of other majors inside beforeMajor', () => {
            const warn = jest.spyOn(console, 'warn').mockImplementation(() => {});

            Feature.branch('V7_0_0_0', {
                beforeMajor: () => Feature.triggerDeprecationOrThrow('V6_9_0_0', 'otherMajorMethod() is deprecated.'),
            });

            expect(warn).toHaveBeenCalledWith('[Deprecation]', 'otherMajorMethod() is deprecated.');
        });

        it('stays silenced until the outermost of nested branches returns', () => {
            const warn = jest.spyOn(console, 'warn').mockImplementation(() => {});

            Feature.branch('V7_0_0_0', {
                beforeMajor: () => {
                    Feature.branch('V7_0_0_0', { beforeMajor: () => undefined });
                    Feature.triggerDeprecationOrThrow('V7_0_0_0', 'nestedMethod() is deprecated.');
                },
            });

            expect(warn).not.toHaveBeenCalled();
            expect(Feature.isSilenced('V7_0_0_0')).toBe(false);
        });

        it('ends the silencing when beforeMajor throws', () => {
            expect(() =>
                Feature.branch('V7_0_0_0', {
                    beforeMajor: () => {
                        throw new Error('legacy path failed');
                    },
                }),
            ).toThrow('legacy path failed');
            expect(Feature.isSilenced('V7_0_0_0')).toBe(false);
        });

        it('throws for an asynchronous beforeMajor, as the silencing ends before it resumes', () => {
            expect(() => Feature.branch('V7_0_0_0', { beforeMajor: () => Promise.resolve() })).toThrow(
                "Feature.branch('V7_0_0_0') silences only synchronous code: await outside of `beforeMajor`.",
            );
            expect(Feature.isSilenced('V7_0_0_0')).toBe(false);
        });
    });

    describe('deprecationBaselineKey', () => {
        it('drops the component path the deprecation plugin appends', () => {
            expect(deprecationBaselineKey('The component "sw-x" is deprecated.\nUsed in: sw-page > sw-x')).toBe(
                'The component "sw-x" is deprecated.',
            );
        });
    });
});

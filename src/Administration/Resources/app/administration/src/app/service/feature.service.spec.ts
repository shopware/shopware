/**
 * @sw-package framework
 */

import FeatureService from 'src/app/service/feature.service';
import Feature from 'src/core/feature';

describe('src/app/service/feature.service', () => {
    const originalEmitDeprecations = Feature.emitDeprecations;

    function createService(activeFlags: string[]): FeatureService {
        return new FeatureService({
            isActive: (flagName: string) => activeFlags.includes(flagName),
        } as unknown as typeof Feature);
    }

    afterEach(() => {
        Feature.emitDeprecations = originalEmitDeprecations;
        jest.restoreAllMocks();
    });

    it('delegates isActive to the wrapped registry', () => {
        expect(createService(['V6_9_0_0']).isActive('V6_9_0_0')).toBe(true);
        expect(createService([]).isActive('V6_9_0_0')).toBe(false);
    });

    it('throws once the major flag is active', () => {
        expect(() => createService(['V6_9_0_0']).triggerDeprecationOrThrow('V6_9_0_0', 'service usage')).toThrow(
            'Tried to access deprecated functionality: service usage',
        );
    });

    it('warns while the major flag is inactive', () => {
        Feature.emitDeprecations = true;
        const warn = jest.spyOn(console, 'warn').mockImplementation(() => {});

        createService([]).triggerDeprecationOrThrow('V6_9_0_0', 'a unique service message');

        expect(warn).toHaveBeenCalledTimes(1);
        expect(warn.mock.calls[0].join(' ')).toContain('a unique service message');
    });

    it('guards through its own isActive, so the Jest feature mock behaves like the real registry', () => {
        const service = createService([]);
        jest.spyOn(service, 'isActive').mockReturnValue(true);

        expect(() => service.triggerDeprecationOrThrow('V6_9_0_0', 'mocked flag')).toThrow(
            'Tried to access deprecated functionality: mocked flag',
        );
    });
});

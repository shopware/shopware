/**
 * Throwaway probe: proves in CI that the Jest job's `FEATURE_ALL` lane decides which majors the
 * feature service sees. Do not merge with the change.
 *
 * @sw-package framework
 */

describe('major lane', () => {
    it('activates only the majors of the lane', () => {
        const lane = process.env.FEATURE_ALL ?? '';

        expect(Shopware.Feature.isActive('v6.8.0.0')).toBe(
            [
                'v6.8.0.0',
                'v6.9.0.0',
                'major',
            ].includes(lane),
        );
        expect(Shopware.Feature.isActive('v6.9.0.0')).toBe(
            [
                'v6.9.0.0',
                'major',
            ].includes(lane),
        );
    });
});

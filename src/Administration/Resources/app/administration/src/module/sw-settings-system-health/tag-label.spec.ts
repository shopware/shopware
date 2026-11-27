/**
 * @sw-package framework
 */
import tagLabel from './tag-label';

describe('module/sw-settings-system-health/tag-label', () => {
    it('should use the snippet of a known tag', () => {
        expect(
            tagLabel(
                'apps',
                (key) => `translated ${key}`,
                () => true,
            ),
        ).toBe('translated sw-settings-system-health.tag.apps');
    });

    it('should capitalize a tag without a snippet', () => {
        expect(
            tagLabel(
                'payments',
                (key) => key,
                () => false,
            ),
        ).toBe('Payments');
    });
});

/**
 * @package admin
 */
describe('src/app/filter/unicode-uri.ts', () => {
    const unicodeUriFilter = Shopware.Filter.getByName('unicodeUri');

    it('should contain a filter', () => {
        expect(unicodeUriFilter).toBeDefined();
    });

    it('should return empty string fallback when no value is given', () => {
        expect(unicodeUriFilter()).toBe('');
    });

    [
        [
            'xn--maana-pta.com',
            'mañana.com',
        ],
        [
            'xn----dqo34k.com',
            '☃-⌘.com',
        ],
        [
            'джумла@xn--p-8sbkgc5ag7bhce.xn--ba-lmcq',
            'джумла@джpумлатест.bрфa',
        ],
    ].forEach(
        ([
            input,
            expected,
        ]) => {
            it(`should return correct result for ${input}`, () => {
                expect(unicodeUriFilter(input)).toBe(expected);
            });
        },
    );
});

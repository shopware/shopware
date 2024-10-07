/**
 * @package admin
 */

const { Filter } = Shopware;

describe('filter/decode-idn-email.filter', () => {
    let emailIdnFilter;

    beforeAll(() => {
        emailIdnFilter = Filter.getByName('decode-idn-email');
    });

    it('should handle ascii email', async () => {
        expect(emailIdnFilter('test@test.com')).toBe('test@test.com');
    });

    it('should handle umlaut email', async () => {
        expect(emailIdnFilter('test@xn--tst-qla.com')).toBe('test@täst.com');
    });

    it('should handle not utf email', async () => {
        expect(emailIdnFilter('test@täst.com')).toBe('test@täst.com');
    });

    it('should handle email with two @', async () => {
        expect(emailIdnFilter('test@täs@t.com')).toBe('test@täs');
    });

    it('should handle without @', async () => {
        expect(emailIdnFilter('testtest.com')).toBe('testtest.com');
    });
});

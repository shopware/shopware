const { canAutoFix, isFixDisabled } = require('./fix-policy');

describe('fix policy', () => {
    it('reads both option forms that turn fixing off', () => {
        expect(isFixDisabled({ options: [] })).toBe(false);
        expect(isFixDisabled({ options: ['enableFix'] })).toBe(false);
        expect(isFixDisabled({ options: ['disableFix'] })).toBe(true);
        expect(isFixDisabled({ options: [{ fix: false }] })).toBe(true);
        expect(isFixDisabled({ options: [{ activatedComponents: [] }] })).toBe(false);
    });

    it('never fixes a manual step', () => {
        const context = { options: [] };

        expect(canAutoFix(context, { fix: 'auto' })).toBe(true);
        expect(canAutoFix(context, { fix: 'unsafe-auto' })).toBe(true);
        expect(canAutoFix(context, { fix: 'manual' })).toBe(false);
        expect(canAutoFix({ options: ['disableFix'] }, { fix: 'auto' })).toBe(false);
    });
});

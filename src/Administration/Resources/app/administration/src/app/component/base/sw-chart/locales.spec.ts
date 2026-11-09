/**
 * @sw-package framework
 */
const mockEnglishEvaluation = jest.fn();
const mockDutchEvaluation = jest.fn();

jest.mock('apexcharts/dist/locales/en.json', () => {
    mockEnglishEvaluation();
    return { name: 'en' };
});
jest.mock('apexcharts/dist/locales/nl.json', () => {
    mockDutchEvaluation();
    return { name: 'nl' };
});

describe('chart locale imports', () => {
    it('does not evaluate locale JSON until its loader is called', async () => {
        const { default: loaders } = await import('./locales');
        expect(mockEnglishEvaluation).not.toHaveBeenCalled();
        expect(mockDutchEvaluation).not.toHaveBeenCalled();

        const locale = await loaders.nl();
        expect(locale.default.name).toBe('nl');
        expect(mockDutchEvaluation).toHaveBeenCalledTimes(1);
        expect(mockEnglishEvaluation).not.toHaveBeenCalled();
    });
});

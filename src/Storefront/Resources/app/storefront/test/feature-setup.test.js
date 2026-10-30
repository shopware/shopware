import Feature from 'src/helper/feature.helper';

const majorIsActive = [process.env.FEATURE_ALL, process.env.V6_8_0_0]
    .some(value => value && value !== '0' && value !== 'false');
const featureAtModuleEvaluation = Feature.isActive('v6.8.0.0');

describe('Storefront major feature setup', () => {
    let deprecatedTestRan = false;
    let deprecatedSuiteRan = false;

    test('activates the major baseline before test modules are evaluated', () => {
        expect(featureAtModuleEvaluation).toBe(majorIsActive);
        expect(window.Feature).toBe(Feature);
    });

    it.deprecated('v6.8.0.0')('runs only before removal', () => {
        deprecatedTestRan = true;
        expect(Feature.isActive('v6.8.0.0')).toBe(false);
    });

    describe.deprecated('v6.8.0.0')('legacy suite', () => {
        test('runs only before removal', () => {
            deprecatedSuiteRan = true;
            expect(Feature.isActive('v6.8.0.0')).toBe(false);
        });
    });

    afterAll(() => {
        expect(deprecatedTestRan).toBe(!majorIsActive);
        expect(deprecatedSuiteRan).toBe(!majorIsActive);
    });
});

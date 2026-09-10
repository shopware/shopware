/**
 * @sw-package framework
 */
import { mount } from '@vue/test-utils';
import useValidation, { type ValidationRules } from './use-validation';

const validationService = {
    required: (value: unknown) => !!value,
    email: (value: unknown) => typeof value === 'string' && value.includes('@'),
};

function createComposable(
    validation: ValidationRules,
    service: unknown = validationService,
): ReturnType<typeof useValidation> {
    let composable: ReturnType<typeof useValidation> | undefined;

    mount(
        {
            template: '<div></div>',
            setup() {
                composable = useValidation({ validation: () => validation });

                return {};
            },
        },
        { global: { provide: { validationService: service } } },
    );

    return composable as ReturnType<typeof useValidation>;
}

describe('src/app/composables/use-validation', () => {
    it('returns a boolean rule as-is', () => {
        expect(createComposable(true).validate('')).toBe(true);
        expect(createComposable(false).validate('anything')).toBe(false);
    });

    it('runs a single rule name against the injected validation service', () => {
        const { validate } = createComposable('required');

        expect(validate('value')).toBe(true);
        expect(validate('')).toBe(false);
    });

    it('splits a comma-separated rule list and requires every rule to pass', () => {
        const { validate } = createComposable('required,email');

        expect(validate('user@example.com')).toBe(true);
        expect(validate('user')).toBe(false);
    });

    it('accepts an array of rules and booleans', () => {
        expect(createComposable(['required']).validate('value')).toBe(true);
        expect(
            createComposable([
                'required',
                'email',
            ]).validate('value'),
        ).toBe(false);
        expect(createComposable([false]).validate('value')).toBe(false);
        expect(createComposable([{ nested: true }] as unknown as ValidationRules).validate('value')).toBe(false);
    });

    it('fails a rule the validation service does not know', () => {
        expect(createComposable('unknownRule').validate('value')).toBe(false);
    });

    it('fails every rule when no validation service was provided', () => {
        const { validate, validationService: injected } = createComposable('required', null);

        expect(injected).toBeNull();
        expect(validate('value')).toBe(false);
    });
});

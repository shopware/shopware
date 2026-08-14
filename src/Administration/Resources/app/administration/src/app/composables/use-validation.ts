/**
 * @sw-package framework
 */
import { inject } from 'vue';

/** @private */
export type ValidationRules = string | boolean | Array<string | boolean> | Record<string, unknown> | null;

type ValidationService = Record<string, (value: unknown) => boolean> | null;

/**
 * The mixin declared the `validation` prop itself; the composable takes it as a
 * getter so the rules stay reactive.
 *
 * @private
 */
export interface UseValidationOptions {
    validation: () => ValidationRules;
}

/** @private */
export interface UseValidationReturn {
    validationService: ValidationService;
    validate: (value: unknown) => boolean;
    validateRule: (value: unknown, rule: string) => boolean;
}

/**
 * Composable alternative to the `validation` mixin: runs a field value against
 * the rules of the `validation` prop.
 *
 * The mixin also exposed an `isValid` computed that read the host's current
 * value under whichever of `currentValue`, `value` or `selections` existed. That
 * name is not knowable up front, so the composable leaves it out — call
 * `validate(...)` with the value instead.
 *
 * Keep this and `src/app/mixin/validation.mixin.ts` in sync — change both together.
 *
 * @private
 */
export function useValidation(options: UseValidationOptions): UseValidationReturn {
    const validationService = inject<ValidationService>('validationService', null);

    function validateRule(value: unknown, rule: string): boolean {
        if (typeof validationService?.[rule] === 'undefined') {
            return false;
        }

        return validationService[rule](value);
    }

    function validate(value: unknown): boolean {
        let validation = options.validation();
        let valid = true;

        if (Shopware.Utils.types.isBoolean(validation)) {
            return validation;
        }

        if (Shopware.Utils.types.isString(validation)) {
            const validationList = validation.split(',');

            if (validationList.length > 1) {
                validation = validationList;
            } else {
                valid = validateRule(value, validation);
            }
        }

        if (Shopware.Utils.types.isArray(validation)) {
            valid = validation.every((validationRule) => {
                if (Shopware.Utils.types.isBoolean(validationRule)) {
                    return validationRule;
                }

                if (Shopware.Utils.types.isString(validationRule)) {
                    return validateRule(value, validationRule.trim());
                }

                return false;
            });
        }

        return valid;
    }

    return { validationService, validate, validateRule };
}

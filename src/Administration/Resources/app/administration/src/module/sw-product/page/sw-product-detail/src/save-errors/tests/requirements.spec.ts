import { describeSaveError, describeSeoUrlErrors } from '../private';

/**
 * @sw-package inventory
 */

/**
 * A translator that knows the given snippets and, like vue-i18n, returns the key for any other.
 */
function createTranslate(snippets: Record<string, string> = {}) {
    return (key: string, values: Record<string, unknown> = {}) =>
        Object.entries(values).reduce(
            (text, [name, value]) => text.replace(`{${name}}`, String(value)),
            snippets[key] ?? key,
        );
}

/**
 * A failed request with the errors the API returned.
 */
function createFailedRequest(errors: Record<string, unknown>[]) {
    return { response: { data: { errors } } };
}

// When the API refuses to save a product or one of its SEO URLs, the product detail page shows the merchant why.
// The messages come from the errors in the API response, translated where the Administration has a snippet for them.
describe('the messages for a failed save', () => {
    /**
     * @requirement A merchant who reuses a product number learns which number is taken.
     */
    it('names the product number that is already taken', () => {
        // ARRANGE
        const translate = createTranslate({
            'sw-product.notification.notificationSaveErrorProductNoAlreadyExists': 'Number {productNo} exists',
        });
        const response = createFailedRequest([
            { code: 'CONTENT__DUPLICATE_PRODUCT_NUMBER', meta: { parameters: { number: 'SW-1' } } },
        ]);

        // ACT
        const notification = describeSaveError(response, translate);

        // ASSERT
        expect(notification).toEqual({ title: 'global.default.error', message: 'Number SW-1 exists' });
    });

    /**
     * @requirement A merchant sees what the API said about any other save error.
     */
    it('shows the detail of the API error', () => {
        // ACT
        const notification = describeSaveError(createFailedRequest([{ detail: 'Price is missing' }]), createTranslate());

        // ASSERT
        expect(notification.message).toBe('Price is missing');
    });

    /**
     * @requirement A merchant is told to check the required fields when the API gives no reason.
     */
    it('falls back to the message about required fields', () => {
        // ACT
        const notification = describeSaveError(undefined, createTranslate());

        // ASSERT
        expect(notification.message).toBe('global.notification.notificationSaveErrorMessageRequiredFieldsInvalid');
    });

    /**
     * @requirement A merchant sees every reason a SEO URL was refused, translated where possible.
     */
    it('shows one message per refused SEO URL error', () => {
        // ARRANGE
        const translate = createTranslate({ 'global.error-codes.SEO_URL_TAKEN': 'This URL is taken' });
        const error = createFailedRequest([{ detail: 'SEO_URL_TAKEN' }, { detail: 'Untranslated detail' }]);

        // ACT
        const messages = describeSeoUrlErrors(error, translate);

        // ASSERT
        expect(messages).toEqual(['This URL is taken', 'Untranslated detail']);
    });

    /**
     * @requirement A merchant still learns that a SEO URL failed when the request broke before the API answered.
     */
    it('shows the error of a request without an API answer', () => {
        // ACT
        const messages = describeSeoUrlErrors({ message: 'Network Error' }, createTranslate());

        // ASSERT
        expect(messages).toEqual(['Network Error']);
    });
});

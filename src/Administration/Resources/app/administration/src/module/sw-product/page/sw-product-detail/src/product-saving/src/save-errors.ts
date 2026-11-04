/**
 * @sw-package inventory
 */

/**
 * Translates a snippet key, like the `$t` of a component.
 *
 * @private
 */
export type Translate = (key: string, values?: Record<string, unknown>) => string;

type ApiError = {
    code?: string;
    detail?: string;
    title?: string;
    meta?: { parameters?: Record<string, unknown> };
};

type FailedRequest = {
    message?: string;
    response?: { data?: { errors?: ApiError[] } };
};

/**
 * The notification for a product that the API refused to save: a taken product number gets its own message,
 * any other error shows the API's detail, or the generic message about invalid required fields.
 *
 * @example
 * createNotificationError(describeSaveError(response, this.$t));
 *
 * @private
 */
export function describeSaveError(
    response: FailedRequest | undefined,
    translate: Translate,
): { title: string; message: string } {
    const error = response?.response?.data?.errors?.[0];

    if (error?.code === 'CONTENT__DUPLICATE_PRODUCT_NUMBER') {
        return {
            title: translate('global.default.error'),
            message: translate('sw-product.notification.notificationSaveErrorProductNoAlreadyExists', {
                productNo: error.meta?.parameters?.number,
            }),
        };
    }

    return {
        title: translate('global.default.error'),
        message: error?.detail ?? translate('global.notification.notificationSaveErrorMessageRequiredFieldsInvalid'),
    };
}

/**
 * One message per error the API returned for a SEO URL it refused to save.
 * An error code with a snippet under `global.error-codes` shows that snippet, any other its detail or title.
 *
 * @example
 * describeSeoUrlErrors(error, this.$t).forEach((message) => createNotificationError({ message }));
 *
 * @private
 */
export function describeSeoUrlErrors(error: FailedRequest, translate: Translate): string[] {
    const apiErrors = error.response?.data?.errors;

    if (!apiErrors) {
        return [error.message || translate('global.notification.unspecifiedSaveErrorMessage')];
    }

    return apiErrors.map((apiError) => {
        const messageKey = `global.error-codes.${apiError.detail}`;
        const translated = translate(messageKey, apiError.meta?.parameters || {});

        if (translated !== messageKey) {
            return translated;
        }

        return apiError.detail || apiError.title || translate('global.notification.unspecifiedSaveErrorMessage');
    });
}

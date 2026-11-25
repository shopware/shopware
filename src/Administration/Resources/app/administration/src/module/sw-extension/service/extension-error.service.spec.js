/**
 * @sw-package checkout
 */
import ExtensionErrorService from './extension-error.service';

const translator = {
    $t: (key) => key,
};

function createService() {
    return new ExtensionErrorService(
        {
            FRAMEWORK__APP_VALIDATION_FAILED: {
                title: 'sw-extension.errors.appValidationFailed',
            },
        },
        {
            title: 'global.default.error',
            message: 'global.notification.unspecifiedSaveErrorMessage',
        },
    );
}

function errorResponse(errors) {
    return { response: { data: { errors } } };
}

describe('src/module/sw-extension/service/extension-error.service', () => {
    it('shows a mapped title with the error detail, keeping its line breaks', () => {
        const notifications = createService().handleErrorResponse(
            errorResponse([
                {
                    code: 'FRAMEWORK__APP_VALIDATION_FAILED',
                    title: 'Bad Request',
                    detail: 'The following permissions are missing:\n- order:read',
                },
            ]),
            translator,
        );

        expect(notifications).toEqual([
            {
                title: 'sw-extension.errors.appValidationFailed',
                message: 'The following permissions are missing:<br>- order:read',
                autoClose: true,
                actions: [],
            },
        ]);
    });

    it('falls back to the default message for an error without detail', () => {
        const notifications = createService().handleErrorResponse(
            errorResponse([{ code: 'SOME_UNKNOWN_CODE' }]),
            translator,
        );

        expect(notifications).toEqual([
            {
                title: 'global.default.error',
                message: 'global.notification.unspecifiedSaveErrorMessage',
                autoClose: true,
                actions: [],
            },
        ]);
    });
});

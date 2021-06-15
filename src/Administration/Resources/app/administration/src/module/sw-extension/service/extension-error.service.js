export default class ExtensionErrorService {
    static get name() {
        return 'extensionErrorService';
    }

    constructor(errorCodes, fallbackError) {
        this.errorCodes = errorCodes;
        this.fallbackError = fallbackError;
    }

    handleErrorResponse(errorResponse, translator) {
        const errors = errorResponse?.response?.data?.errors ?? [];

        if (!Array.isArray(errors)) {
            return [];
        }

        return errors.map((error) => {
            return this.handleError(error, translator);
        });
    }

    handleError(error, translator) {
        return this._translateRawNotification(
            this._getNotification(error, translator),
            translator,
        );
    }

    _getNotification(error) {
        return this.errorCodes[error.code] ?
            this.errorCodes[error.code] :
            {
                title: error.title || this.fallbackError.title,
                message: error.detail || this.fallbackError.detail,
            };
    }

    _translateRawNotification(notification, translator) {
        return {
            title: translator.$tc(notification.title),
            message: translator.$tc(notification.message),
            autoClose: notification.autoClose || true,
        };
    }
}

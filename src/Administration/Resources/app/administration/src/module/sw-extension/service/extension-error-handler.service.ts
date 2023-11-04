type MappedError = {
    title: string,
    message: string,
    parameters?: {
        documentationLink: string,
    }
}

class StoreError {
    // eslint-disable-next-line no-useless-constructor
    constructor(public readonly title: string, public readonly message: string) {}
}

const errorCodes: { [key: string]: StoreError} = {
    FRAMEWORK__PLUGIN_NO_PLUGIN_FOUND_IN_ZIP: new StoreError(
        'global.default.error',
        'sw-extension.errors.messageUploadFailureNoPluginFoundInZipFile',
    ),
    FRAMEWORK__PLUGIN_NOT_A_ZIP_FILE: new StoreError(
        'global.default.error',
        'sw-extension.errors.messageUploadFailureNotAZipFile',
    ),
    FRAMEWORK__PLUGIN_EXTRACTION_FAILED: new StoreError(
        'global.default.error',
        'sw-extension.errors.messageUploadFailureUnzipFailed',
    ),
    FRAMEWORK__STORE_INVALID_CREDENTIALS: new StoreError(
        'global.default.error',
        'sw-extension.errors.messageLoginDataInvalid',
    ),
    FRAMEWORK__STORE_LICENSE_DOMAIN_IS_MISSING: new StoreError(
        'global.default.error',
        'sw-extension.errors.messageStoreLicenseDomainMissing',
    ),
    FRAMEWORK__STORE_NOT_AVAILABLE: new StoreError(
        'global.default.error',
        'sw-extension.errors.messageStoreNotAvailable',
    ),
    FRAMEWORK__PLUGIN_BASE_CLASS_NOT_FOUND: new StoreError(
        'global.default.error',
        'sw-extension.errors.messagePluginBaseClassNotFound',
    ),
    FRAMEWORK__PLUGIN_REQUIREMENT_MISMATCH: new StoreError(
        'global.default.error',
        'sw-extension.errors.messagePluginRequirementMismatch',
    ),
};

function getNotification(error: StoreApiException): MappedError {
    if (error.code === 'FRAMEWORK__STORE_ERROR') {
        return mapErrorWithDocsLink(error);
    }

    if (typeof errorCodes[error.code] !== 'undefined') {
        return {
            title: errorCodes[error.code].title,
            message: errorCodes[error.code].message,
        };
    }

    return {
        title: 'global.default.error',
        message: 'sw-extension.errors.messageGenericFailure',
    };
}

function mapErrorWithDocsLink({ title, detail: message, meta }: StoreApiException): MappedError {
    if (meta && typeof meta.documentationLink === 'string') {
        return {
            title,
            message,
            parameters: {
                documentationLink: meta.documentationLink,
            },
        };
    }

    return {
        title,
        message,
    };
}

function mapErrors(errors: StoreApiException[]) {
    return errors.map(getNotification);
}

/**
 * @package merchant-services
 * @private
 */
export default {
    mapErrors,
};

/**
 * @package merchant-services
 * @private
 */
export type {
    MappedError,
};

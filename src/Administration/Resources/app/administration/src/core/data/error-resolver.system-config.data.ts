import ShopwareError from 'src/core/data/ShopwareError';

const { string } = Shopware.Utils;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
interface ApiError {
    code: string;
    title: string;
    detail: string;
    meta: {
        parameters: object;
    };
    status: string;
    source?: {
        pointer?: string;
    };
}

/**
 * @package system-settings
 *
 * @deprecated tag:v6.6.0 - Will be private
 */
export default class ErrorResolverSystemConfig {
    public static ENTITY_NAME = 'SYSTEM_CONFIG';

    private readonly merge;

    constructor() {
        this.merge = Shopware.Utils.object.merge;
    }

    public handleWriteErrors(errors?: ApiError[]) {
        if (!errors) {
            throw new Error('[error-resolver] handleWriteError was called without errors');
        }

        const writeErrors = this.reduceErrorsByWriteIndex(errors);

        if (writeErrors.systemError.length > 0) {
            this.addSystemErrors(writeErrors.systemError);
        }

        this.handleErrors(writeErrors.apiError);
    }

    public cleanWriteErrors() {
        void Shopware.State.dispatch('error/resetApiErrors');
    }

    private reduceErrorsByWriteIndex(errors: ApiError[]) {
        const writeErrors: {
            systemError: ShopwareError[],
            apiError: {
                [key: string]: ShopwareError
            },
        } = {
            systemError: [],
            apiError: {},
        };

        errors.forEach((current) => {
            if (!current.source || !current.source.pointer) {
                const systemError = new ShopwareError({
                    code: current.code,
                    meta: current.meta,
                    detail: current.detail,
                    status: current.status,
                });
                writeErrors.systemError.push(systemError);

                return;
            }

            const segments = current.source.pointer.split('/');

            // remove first empty element in list
            if (segments[0] === '') {
                segments.shift();
            }

            const denormalized = {};
            const lastIndex = segments.length - 1;

            segments.reduce((pointer: {[key: string]: Partial<ShopwareError> }, segment, index) => {
                // skip translations
                if (segment === 'translations' || segments[index - 1] === 'translations') {
                    return pointer;
                }

                if (index === lastIndex) {
                    pointer[segment] = new ShopwareError(current);
                } else {
                    pointer[segment] = {};
                }

                return pointer[segment];
            }, denormalized);

            writeErrors.apiError = this.merge(writeErrors.apiError, denormalized);
        });

        return writeErrors;
    }

    private addSystemErrors(errors: ShopwareError[]) {
        errors.forEach((error) => {
            void Shopware.State.dispatch('error/addSystemError', error);
        });
    }

    private handleErrors(errors: {[key: string]: ShopwareError}) {
        Object.keys(errors).forEach((key: string) => {
            void Shopware.State.dispatch('error/addApiError', {
                expression: this.getErrorPath(key),
                error: errors[key],
            });
        });
    }

    private getErrorPath(key: string) {
        key = string.camelCase(key);

        return `${ErrorResolverSystemConfig.ENTITY_NAME}.${key}`;
    }
}

/**
 * @private
 * The name the mediaName filter is registered under, for code that looks it up with `Shopware.Filter.getByName()`.
 */
export const mediaNameFilterName = 'mediaName';

/**
 * @sw-package discovery
 */
Shopware.Filter.register(
    mediaNameFilterName,
    (
        value: {
            entity?: {
                fileName?: string;
                fileExtension?: string;
            };
            fileName?: string;
            fileExtension?: string;
        },
        fallback: string = '',
    ): string => {
        if (!value) {
            return fallback;
        }

        if (value.entity) {
            value = value.entity;
        }

        if (!value.fileName || !value.fileExtension) {
            return fallback;
        }

        return `${value.fileName}.${value.fileExtension}`;
    },
);

/* @private */
export {};

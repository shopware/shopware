/**
 * @package content
 */
Shopware.Filter.register('mediaName', (
    value: {
    entity?: {
        fileName?: string,
        fileExtension?: string
    },
    fileName?: string,
    fileExtension?: string
},
    // eslint-disable-next-line @typescript-eslint/no-inferrable-types
    fallback: string = '',
): string => {
    if (!value) {
        return fallback;
    }

    if (value.entity) {
        value = value.entity;
    }

    if ((!value.fileName) || (!value.fileExtension)) {
        return fallback;
    }

    return `${value.fileName}.${value.fileExtension}`;
});

/* @private */
export {};

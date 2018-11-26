import { Filter } from 'src/core/shopware';

Filter.register('mediaName', (value, fallback = '') => {
    if (!value || !(value.type === 'media') || value.fileName === null || value.fileExtension === null) {
        return fallback;
    }

    return `${value.fileName}.${value.fileExtension}`;
});

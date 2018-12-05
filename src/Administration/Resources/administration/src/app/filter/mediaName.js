import { Filter } from 'src/core/shopware';

Filter.register('mediaName', (value, fallback = '') => {
    if (!value || !(value.type === 'media')) {
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

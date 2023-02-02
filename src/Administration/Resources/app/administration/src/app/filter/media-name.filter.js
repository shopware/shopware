const { Filter } = Shopware;

Filter.register('mediaName', (value, fallback = '') => {
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

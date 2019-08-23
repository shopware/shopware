const { Filter } = Shopware;
const { fileSize } = Shopware.Utils.format;

Filter.register('fileSize', (value, locale) => {
    if (!value) {
        return '';
    }

    return fileSize(value, locale);
});

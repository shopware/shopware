import { Filter } from 'src/core/shopware';

Filter.register('mediaName', (value) => {
    if (!value || !(value.type === 'media')) {
        return '';
    }

    return `${value.fileName}.${value.fileExtension}`;
});

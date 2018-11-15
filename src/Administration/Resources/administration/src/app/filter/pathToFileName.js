import { Filter } from 'src/core/shopware';

Filter.register('pathToFileName', (filePath) => {
    if (!filePath) {
        return '';
    }

    return filePath.split('/').pop();
});

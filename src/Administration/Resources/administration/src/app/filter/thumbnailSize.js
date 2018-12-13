import { Filter } from 'src/core/shopware';

Filter.register('thumbnailSize', (value) => {
    if (!value || !(value.entityName === 'media_thumbnail_size')) {
        return '';
    }

    if ((!value.width) || (!value.height)) {
        return '';
    }

    return `${value.width}x${value.height}`;
});

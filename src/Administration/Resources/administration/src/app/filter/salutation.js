import { Filter } from 'src/core/shopware';

Filter.register('salutation', (entity, fallbackSnippet = '') => {
    if (!entity.salutation) {
        return fallbackSnippet;
    }

    const params = {
        salutation: entity.salutation.displayName || '',
        title: entity.title || '',
        firstName: entity.firstName || '',
        lastName: entity.lastName || ''
    };

    const fullname = Object.values(params).join(' ').replace(/\s+/g, ' ').trim();

    if (fullname === '') {
        return fallbackSnippet;
    }

    return fullname;
});

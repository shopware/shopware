/**
 * @package admin
 */

const { Filter, Defaults } = Shopware;

type SalutationType = {
    id: string,
    salutationKey: string,
    displayName: string
};

Filter.register('salutation', (
    entity: { salutation: SalutationType, title: string, firstName: string, lastName: string, [key: string]: unknown },
    fallbackSnippet = '',
): string => {
    if (!entity) {
        return fallbackSnippet;
    }

    let hideSalutation = true;

    if (entity.salutation && entity.salutation.id !== Defaults.defaultSalutationId) {
        hideSalutation = [
            'not_specified',
        ].some((item) => item === entity.salutation.salutationKey);
    }

    const params = {
        salutation: !hideSalutation ? entity.salutation.displayName : '',
        title: entity.title || '',
        firstName: entity.firstName || '',
        lastName: entity.lastName || '',
    };

    const fullName = Object.values(params).join(' ').replace(/\s+/g, ' ').trim();

    if (fullName === '') {
        return fallbackSnippet;
    }

    return fullName;
});

/**
 * @deprecated tag:v6.6.0 - Will be private
 */
export default {};


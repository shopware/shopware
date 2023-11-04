const { Criteria } = Shopware.Data;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default class FeatureGridTranslationService {
    /**
     * @param {Vue} component
     * @param {Repository} propertyGroupRepository
     * @param {Repository} customFieldRepository
     */
    constructor(component, propertyGroupRepository, customFieldRepository) {
        this.component = component;
        this.propertyGroupRepository = propertyGroupRepository;
        this.customFieldRepository = customFieldRepository;

        this.entities = {
            property: [],
            customField: [],
        };
    }

    /**
     * @param {array} features
     */
    fetchPropertyGroupEntities(features) {
        return this._fetchEntities(features, 'property', 'id', this.propertyGroupRepository);
    }

    /**
     * @param {array} features
     */
    fetchCustomFieldEntities(features) {
        return this._fetchEntities(features, 'customField', 'name', this.customFieldRepository);
    }

    /**
     * @private
     *
     * @param {array} features
     * @param {string} type
     * @param {string} filterBy
     * @param {Repository} repo
     */
    _fetchEntities(features, type, filterBy, repo) {
        if (!features || features.length < 1) {
            return Promise.resolve();
        }

        const identifier = features.filter(value => value.type === type).map(value => value[filterBy]);

        if (identifier.length < 1) {
            return Promise.resolve();
        }

        const criteria = new Criteria(1, 25);

        criteria.addFilter(Criteria.equalsAny(
            filterBy,
            identifier,
        ));

        return repo.search(criteria, Shopware.Context.api).then((items) => {
            this.entities[type] = items;
        });
    }

    /**
     * @param {Object} item
     * @param {string} item.type
     * @param {string} item.name
     * @param {string} item.id
     */
    getNameTranslation(item) {
        if (item.type === 'product') {
            return this.component.$tc(`sw-settings-product-feature-sets.modal.label.${item.name}`);
        }

        if (item.type === 'property') {
            return this.entities.property
                .filter(group => group.id === item.id)
                .map(group => group.translated.name)
                .pop();
        }

        if (item.type === 'customField') {
            const language = Shopware.State.get('session').currentLocale;
            const fallback = Shopware.Context.app.fallbackLocale;

            return this.entities.customField
                .filter(field => field.name === item.name)
                .map(field => (field.config.label[language] || field.config.label[fallback]))
                .pop();
        }

        if (item.type === 'referencePrice') {
            return this.component.$tc('sw-settings-product-feature-sets.modal.label.referencePrice');
        }

        return '';
    }

    /**
     * @param {Object} item
     * @param {string} item.type
     * @param {string} item.name
     */
    getTypeTranslation(item) {
        if (item.type === 'product') {
            return this.component.$tc('sw-settings-product-feature-sets.modal.textProductInfoLabel');
        }

        if (item.type === 'property') {
            return this.component.$tc('sw-settings-product-feature-sets.modal.textPropertyLabel');
        }

        if (item.type === 'customField') {
            return this.component.$tc('sw-settings-product-feature-sets.modal.textCustomFieldLabel');
        }

        if (item.type === 'referencePrice') {
            return this.component.$tc('sw-settings-product-feature-sets.modal.textReferencePriceLabel');
        }

        return '';
    }
}

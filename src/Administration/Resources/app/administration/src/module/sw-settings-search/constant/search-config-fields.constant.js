/**
 * @sw-package inventory
 */

/**
 * Field → `generalTab.configFields.*` snippet key. Single source for the
 * searchable-content table and the live-search explain panel.
 *
 * @private
 */
export const SEARCH_CONFIG_FIELD_SNIPPETS = Object.freeze({
    name: 'name',
    'parent.name': 'parentName',
    description: 'description',
    productNumber: 'productNumber',
    manufacturerNumber: 'manufacturerNumber',
    ean: 'ean',
    customSearchKeywords: 'customSearchKeywords',
    'manufacturer.name': 'manufacturerName',
    'manufacturer.customFields': 'manufacturerCustomFields',
    'categories.name': 'categoriesName',
    'categories.customFields': 'categoriesCustomFields',
    'tags.name': 'tagsName',
    metaTitle: 'metaTitle',
    metaDescription: 'metaDescription',
    'properties.name': 'propertiesValue',
    'options.name': 'variantValue',
});

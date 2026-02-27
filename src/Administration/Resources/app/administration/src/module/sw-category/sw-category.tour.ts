/**
 * @sw-package discovery
 */

const swCategoryTourSteps = {
    default: [
        {
            selector: '.sw-category-detail-page__tabs',
            title: 'Category sections',
            text: 'Lol, I have tabs',
        },
        {
            selector: '.mt-card[aria-label="General"]',
            title: 'General category settings',
            text: 'Nice general card, bre.',
        },
        {
            selector: '.sw-category-detail-base__active',
            title: 'Active',
            text: 'Switchy switch is switching stuff',
        },
        {
            selector: '.mt-text-editor__box',
            title: 'Something down below',
            text: 'I am somewhere down below?',
        },
        {
            route: { name: 'sw.settings.language.index' },
            selector: '.sw-settings-language-list__button-create',
            waitFor: '.sw-settings-language-list__button-create',
            title: 'Languages',
            text: 'Create or manage languages here.',
        },
    ],
    detail: [
        {
            selector: '.sw-category-detail__save-action',
            title: 'Save changes',
            text: 'Save your category changes here.',
        },
        {
            selector: '.sw-category-detail-base__active',
            title: 'Visibility',
            text: 'Toggle whether the category is active.',
        },
        {
            selector: '.sw-category-detail-page__tabs',
            title: 'Navigate sections',
            text: 'Switch between base, products, layout and SEO.',
        },
    ],
};

/**
 * @private
 */
export default swCategoryTourSteps;

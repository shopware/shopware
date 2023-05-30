import { shallowMount } from '@vue/test-utils';

import swGenericSeoGeneralCard from 'src/module/sw-custom-entity/component/sw-generic-seo-general-card';

Shopware.Component.register('sw-generic-seo-general-card', swGenericSeoGeneralCard);

const TEST_SEO_META_TITLE = 'TEST_SEO_META_TITLE';
const TEST_SEO_META_DESCRIPTION = 'TEST_SEO_META_DESCRIPTION';
const TEST_SEO_META_URL = 'TEST_SEO_META_URL';

async function createWrapper() {
    return shallowMount(await Shopware.Component.build('sw-generic-seo-general-card'), {
        stubs: {
            'sw-card': true,
            'sw-text-field': {
                template: '<input class="sw-text-field" :value="value" @input="$emit(\'input\', $event.target.value)" />',
                props: ['value', 'label', 'help-text', 'placeholder', 'maxlength'],
            },
            'sw-textarea-field': {
                template: '<textarea class="sw-text-field" :value="value" @input="$emit(\'input\', $event.target.value)" />',
                props: ['value', 'label', 'help-text', 'placeholder', 'maxlength'],
            },
        },
    });
}

/**
 * @package content
 */
describe('src/module/sw-custom-entity/component/sw-generic-seo-general-card', () => {
    it('should display the seoMetaTitle and allow changing it', async () => {
        const wrapper = await createWrapper();

        const seoMetaTitleInput = wrapper.get('.sw-generic-seo-general-card__seo-meta-title-input');
        const seoMetaTitleDisplay = wrapper.get('.sw-generic-seo-general-card__google-preview-title');

        expect(seoMetaTitleInput.props('placeholder')).toBe('sw-landing-page.base.seo.placeholderMetaTitle');
        expect(seoMetaTitleInput.props('helpText')).toBe('sw-landing-page.base.seo.helpTextMetaTitle');
        expect(seoMetaTitleInput.props('label')).toBe('sw-landing-page.base.seo.labelMetaTitle');
        expect(seoMetaTitleInput.props('maxlength')).toBe('255');

        expect(seoMetaTitleInput.props('value')).toBe('');
        expect(seoMetaTitleDisplay.text()).toBe('');

        await seoMetaTitleInput.setValue(TEST_SEO_META_TITLE);
        expect(wrapper.emitted()).toEqual({ 'update:seo-meta-title': [[TEST_SEO_META_TITLE]] });

        await wrapper.setProps({
            seoMetaTitle: TEST_SEO_META_TITLE,
        });

        expect(seoMetaTitleInput.props('value')).toBe(TEST_SEO_META_TITLE);
        expect(seoMetaTitleDisplay.text()).toBe(TEST_SEO_META_TITLE);
    });

    it('should display the seoMetaDescription and allow changing it', async () => {
        const wrapper = await createWrapper();

        const seoMetaDescriptionInput = wrapper.get('.sw-generic-seo-general-card__seo-meta-description-input');
        const seoMetaDescriptionDisplay = wrapper.get('.sw-generic-seo-general-card__google-preview-description');

        expect(seoMetaDescriptionInput.props('placeholder')).toBe('sw-landing-page.base.seo.placeholderMetaDescription');
        expect(seoMetaDescriptionInput.props('helpText')).toBe('sw-landing-page.base.seo.helpTextMetaDescription');
        expect(seoMetaDescriptionInput.props('label')).toBe('sw-landing-page.base.seo.labelMetaDescription');
        expect(seoMetaDescriptionInput.props('maxlength')).toBe('255');

        expect(seoMetaDescriptionInput.props('value')).toBe('');
        expect(seoMetaDescriptionDisplay.text()).toBe('');

        await seoMetaDescriptionInput.setValue(TEST_SEO_META_DESCRIPTION);
        expect(wrapper.emitted()).toEqual({ 'update:seo-meta-description': [[TEST_SEO_META_DESCRIPTION]] });

        await wrapper.setProps({
            seoMetaDescription: TEST_SEO_META_DESCRIPTION,
        });

        expect(seoMetaDescriptionInput.props('value')).toBe(TEST_SEO_META_DESCRIPTION);
        expect(seoMetaDescriptionDisplay.text()).toBe(TEST_SEO_META_DESCRIPTION);
    });

    it('should display the seoUrl and allow changing it', async () => {
        const seoUrlPrefix = 'https://www.example.com >';
        const wrapper = await createWrapper();

        const seoUrlInput = wrapper.get('.sw-generic-seo-general-card__seo-url-input');
        const seoUrlDisplay = wrapper.get('.sw-generic-seo-general-card__google-preview-link');

        expect(seoUrlInput.props('placeholder')).toBe('sw-landing-page.base.seo.placeholderUrl');
        expect(seoUrlInput.props('label')).toBe('sw-landing-page.base.seo.labelUrl');
        expect(seoUrlInput.props('maxlength')).toBe('255');

        expect(seoUrlInput.props('value')).toBe('');
        expect(seoUrlDisplay.text()).toBe(seoUrlPrefix);

        await seoUrlInput.setValue(TEST_SEO_META_URL);
        expect(wrapper.emitted()).toEqual({ 'update:seo-url': [[TEST_SEO_META_URL]] });

        await wrapper.setProps({
            seoUrl: TEST_SEO_META_URL,
        });

        expect(seoUrlInput.props('value')).toBe(TEST_SEO_META_URL);
        expect(seoUrlDisplay.text()).toBe(`${seoUrlPrefix} ${TEST_SEO_META_URL}`);
    });
});

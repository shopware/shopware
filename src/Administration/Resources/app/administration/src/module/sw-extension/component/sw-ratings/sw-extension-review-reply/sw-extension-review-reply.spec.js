import { shallowMount } from '@vue/test-utils';
import swExtensionReviewReply from 'src/module/sw-extension/component/sw-ratings/sw-extension-review-reply';

Shopware.Component.register('sw-extension-review-reply', swExtensionReviewReply);

/**
 * @package services-settings
 */
describe('src/module/sw-extension/component/sw-ratings/sw-extension-review-reply', () => {
    /** @type Wrapper */
    let wrapper;

    async function createWrapper() {
        return shallowMount(await Shopware.Component.build('sw-extension-review-reply'), {
            propsData: {
                producerName: 'Howard Wolowitz',
                reply: {
                    text: 'Lorem ipsum dolor sit amet.',
                    creationDate: {
                        date: '2021-01-11 08:10:08.000000',
                        timezone_type: 1,
                        timezone: '+01:00',
                    },
                },
            },
        });
    }

    afterEach(() => {
        if (wrapper) wrapper.destroy();
    });

    it('should be a Vue.js component', async () => {
        wrapper = await createWrapper();
        expect(wrapper.vm).toBeTruthy();
    });

    it('should display the extension creator name', async () => {
        wrapper = await createWrapper();
        const creatorName = await wrapper.find('.sw-extension-review-reply__producer-name');

        expect(creatorName.text()).toBe('Howard Wolowitz');
    });

    it('should display the actual content of the reply', async () => {
        wrapper = await createWrapper();
        const replyContent = await wrapper.find('.sw-extension-review-reply__text');

        expect(replyContent.text()).toBe('Lorem ipsum dolor sit amet.');
    });
});

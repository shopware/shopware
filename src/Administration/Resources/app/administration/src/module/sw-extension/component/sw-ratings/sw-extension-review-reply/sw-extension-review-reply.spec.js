import { mount } from '@vue/test-utils';

/**
 * @package checkout
 */
describe('src/module/sw-extension/component/sw-ratings/sw-extension-review-reply', () => {
    async function createWrapper() {
        return mount(
            await wrapTestComponent('sw-extension-review-reply', {
                sync: true,
            }),
            {
                props: {
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
            },
        );
    }

    it('should display the extension creator name', async () => {
        const wrapper = await createWrapper();
        const creatorName = await wrapper.find('.sw-extension-review-reply__producer-name');

        expect(creatorName.text()).toBe('Howard Wolowitz');
    });

    it('should display the actual content of the reply', async () => {
        const wrapper = await createWrapper();
        const replyContent = await wrapper.find('.sw-extension-review-reply__text');

        expect(replyContent.text()).toBe('Lorem ipsum dolor sit amet.');
    });
});

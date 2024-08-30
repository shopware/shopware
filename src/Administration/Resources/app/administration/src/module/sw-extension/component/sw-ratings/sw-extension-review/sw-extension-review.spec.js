import { mount } from '@vue/test-utils';

/**
 * @package checkout
 */
describe('src/module/sw-extension/component/sw-ratings/sw-extension-review', () => {
    async function createWrapper() {
        return mount(await wrapTestComponent('sw-extension-review', { sync: true }), {
            global: {
                stubs: {
                    'sw-extension-rating-stars': await wrapTestComponent('sw-extension-rating-stars'),
                    'sw-icon': true,
                    'sw-extension-review-reply': true,
                },
            },
            props: {
                producerName: 'Bob Ross',
                review: {
                    extensionId: null,
                    headline: 'Can only recommend this plugin!',
                    authorName: 'Einstein',
                    rating: 5,
                    text: 'Bla bla blib blub bla',
                    lastChangeDate: '2020-12-12T10:48:03.000+01:00',
                    version: null,
                    acceptGuidelines: null,
                    replies: [
                        {
                            text: 'Oh my god! Thank you sooooo much for your reply. :)',
                            creationDate: {
                                date: '2021-01-13 08:12:08.000000',
                                timezone_type: 1,
                                timezone: '+01:00',
                            },
                        },
                    ],
                    extensions: [],
                },
            },
        });
    }

    it('should display the review title', async () => {
        const wrapper = await createWrapper();
        const reviewTitle = wrapper.find('.sw-extension-review__headline').text();

        expect(reviewTitle).toBe('Can only recommend this plugin!');
    });

    it('should display the review text', async () => {
        const wrapper = await createWrapper();
        const reviewText = wrapper.find('.sw-extension-review p').text();

        expect(reviewText).toBe('Bla bla blib blub bla');
    });

    it('should have the right amount of replies', async () => {
        const wrapper = await createWrapper();
        const amountOfReplies = wrapper.findAll('sw-extension-review-reply-stub').length;

        expect(amountOfReplies).toBe(1);
    });
});

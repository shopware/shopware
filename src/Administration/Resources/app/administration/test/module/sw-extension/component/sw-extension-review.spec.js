import { shallowMount } from '@vue/test-utils';
import 'src/module/sw-extension/component/sw-ratings/sw-extension-review';
import 'src/module/sw-extension/component/sw-ratings/sw-extension-rating-stars';

describe('src/module/sw-extension/component/sw-ratings/sw-extension-review', () => {
    /** @type Wrapper */
    let wrapper;

    function createWrapper() {
        return shallowMount(Shopware.Component.build('sw-extension-review'), {
            propsData: {
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
                                timezone: '+01:00'
                            }
                        }
                    ],
                    extensions: []
                }
            },
            stubs: {
                'sw-extension-rating-stars': Shopware.Component.build('sw-extension-rating-stars'),
                'sw-icon': true,
                'sw-extension-review-reply': true
            }
        });
    }

    afterEach(() => {
        if (wrapper) wrapper.destroy();
    });

    it('should be a Vue.js component', () => {
        wrapper = createWrapper();
        expect(wrapper.vm).toBeTruthy();
    });

    it('should display the review title', () => {
        wrapper = createWrapper();
        const reviewTitle = wrapper.find('.sw-extension-review__headline').text();

        expect(reviewTitle).toBe('Can only recommend this plugin!');
    });

    it('should display the review text', () => {
        wrapper = createWrapper();
        const reviewText = wrapper.find('.sw-extension-review p').text();

        expect(reviewText).toBe('Bla bla blib blub bla');
    });

    it('should have the right amount of replies', () => {
        wrapper = createWrapper();
        const amountOfReplies = wrapper.findAll('sw-extension-review-reply-stub').length;

        expect(amountOfReplies).toBe(1);
    });
});

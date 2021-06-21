import { shallowMount } from '@vue/test-utils';
import 'src/module/sw-extension/component/sw-ratings/sw-extension-ratings-summary';
import 'src/app/component/utils/sw-progress-bar';

describe('src/module/sw-extension/component/sw-ratings/sw-extension-ratings-summary', () => {
    function createWrapper() {
        return shallowMount(Shopware.Component.build('sw-extension-ratings-summary'), {
            propsData: {
                summary: {
                    ratingAssignment: [
                        { rating: 5, count: 5 },
                        { rating: 4, count: 10 },
                        { rating: 3, count: 2 },
                        { rating: 2, count: 1 },
                        { rating: 1, count: 2 }
                    ],
                    averageRating: 5,
                    numberOfRatings: 20,
                    extensions: []
                }
            },
            stubs: {
                'sw-extension-rating-stars': true,
                'sw-progress-bar': Shopware.Component.build('sw-progress-bar')
            }
        });
    }

    it('should be a Vue.js component', () => {
        const wrapper = createWrapper();
        expect(wrapper.vm).toBeTruthy();
    });

    it('should display amount of ratings correctly', () => {
        const wrapper = createWrapper();

        const amounts = wrapper.findAll('.sw-extension-ratings-summary__progress-bars >:first-child span');

        expect(amounts.at(0).text()).toBe('5');
        expect(amounts.at(1).text()).toBe('10');
        expect(amounts.at(2).text()).toBe('2');
        expect(amounts.at(3).text()).toBe('1');
        expect(amounts.at(4).text()).toBe('2');
    });

    it('should display with of progress bars correctly', () => {
        const wrapper = createWrapper();

        const progressBarValues = wrapper.findAll('.sw-progress-bar__value');

        expect(progressBarValues.at(0).attributes('style')).toBe('width: 25%;');
        expect(progressBarValues.at(1).attributes('style')).toBe('width: 50%;');
        expect(progressBarValues.at(2).attributes('style')).toBe('width: 10%;');
        expect(progressBarValues.at(3).attributes('style')).toBe('width: 5%;');
        expect(progressBarValues.at(4).attributes('style')).toBe('width: 10%;');
    });
});

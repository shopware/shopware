import { shallowMount } from '@vue/test-utils';
import swExtensionRatingsCard from 'src/module/sw-extension/component/sw-ratings/sw-extension-ratings-card';

Shopware.Component.register('sw-extension-ratings-card', swExtensionRatingsCard);

/**
 * @package merchant-services
 */
describe('src/module/sw-extension/component/sw-ratings/sw-extension-ratings-card', () => {
    /** @type Wrapper */
    let wrapper;

    async function createWrapper(noReviews = false) {
        const reviewsAndSummary = noReviews ? { reviews: [], summary: {} } : {
            reviews: [
                {
                    extensionId: null,
                    headline: 'Nice Plugin',
                    authorName: 'Random user',
                    rating: 5,
                    text: 'Is a very good plugin.',
                    lastChangeDate: '2020-12-14T10:00:00.000+01:00',
                    version: null,
                    acceptGuidelines: null,
                    replies: [],
                    extensions: [],
                },
            ],
            summary: {
                averageRating: 5,
                extensions: [],
                numberOfRatings: 1,
                ratingAssignments: [
                    { count: 1, rating: 5 },
                    { count: 0, rating: 4 },
                    { count: 0, rating: 3 },
                    { count: 0, rating: 2 },
                    { count: 0, rating: 1 },
                ],
            },
        };

        return shallowMount(await Shopware.Component.build('sw-extension-ratings-card'), {
            propsData: {
                isInstalledAndLicensed: false,
                producerName: 'Sir Robert Bryson Hall II',
                extension: {
                    id: 'extension-id',
                },
            },
            stubs: {
                'sw-meteor-card': {
                    template: `<div>
    <slot name="default"></slot>
    <slot name="footer"></slot>
</div>`,
                },
                'sw-extension-ratings-summary': true,
                'sw-extension-review': true,
                'sw-extension-review-creation': true,
                'sw-extension-review-creation-inputs': true,
                'sw-gtc-checkbox': true,
                'sw-button': true,
                'sw-button-process': true,
            },
            computed: {
                extensionStoreDataService: () => ({
                    getReviews() {
                        return Promise.resolve(reviewsAndSummary);
                    },
                }),
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

    it('should display empty state when there are no ratings', async () => {
        wrapper = await createWrapper(true);

        expect(wrapper.text())
            .toBe(
                'sw-extension-store.component.sw-extension-ratings.sw-extension-ratings-card.labelNoReviews',
            );
    });
});

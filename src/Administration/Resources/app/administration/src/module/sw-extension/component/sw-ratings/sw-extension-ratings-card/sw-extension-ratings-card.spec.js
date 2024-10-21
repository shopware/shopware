import { mount } from '@vue/test-utils';

/**
 * @package checkout
 */
describe('src/module/sw-extension/component/sw-ratings/sw-extension-ratings-card', () => {
    async function createWrapper(noReviews = false) {
        const reviewsAndSummary = noReviews
            ? { reviews: [], summary: {} }
            : {
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

        return mount(
            await wrapTestComponent('sw-extension-ratings-card', {
                sync: true,
            }),
            {
                global: {
                    computed: {
                        extensionStoreDataService: () => ({
                            getReviews() {
                                return Promise.resolve(reviewsAndSummary);
                            },
                        }),
                    },
                    stubs: {
                        'sw-extension-ratings-summary': true,
                        'sw-extension-review': true,
                        'sw-extension-review-creation': true,
                        'sw-button': true,
                        'sw-meteor-card': {
                            template: '<div><slot></slot></div>',
                        },
                    },
                },
                props: {
                    isInstalledAndLicensed: false,
                    producerName: 'Sir Robert Bryson Hall II',
                    extension: {
                        id: 'extension-id',
                    },
                },
            },
        );
    }

    it('should display empty state when there are no ratings', async () => {
        const wrapper = await createWrapper(true);

        expect(wrapper.text()).toBe(
            'sw-extension-store.component.sw-extension-ratings.sw-extension-ratings-card.labelNoReviews',
        );
    });
});

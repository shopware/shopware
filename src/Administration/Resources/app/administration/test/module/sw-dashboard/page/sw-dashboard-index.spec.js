import { shallowMount, createLocalVue } from '@vue/test-utils';
import 'src/module/sw-dashboard/page/sw-dashboard-index';
import dictionary from 'src/module/sw-dashboard/snippet/en-GB.json';
import { currency } from 'src/core/service/utils/format.utils';

function createWrapper(privileges = [], orderSumToday = null) {
    const localVue = createLocalVue();
    localVue.filter('asset', v => v);
    localVue.filter('date', v => v);
    localVue.filter('currency', currency);

    const responseMock = [{}, {}];
    responseMock.aggregations = {
        order_count_month: {
            buckets: []
        }
    };

    const options = {
        localVue,
        stubs: {
            'sw-page': true,
            'sw-card': true,
            'sw-card-view': true,
            'sw-dashboard-external-link': true,
            'sw-external-link': true,
            'sw-container': true,
            'sw-button': true,
            'sw-entity-listing': true,
            'sw-chart': true,
            'sw-icon': true
        },
        mocks: {
            $tc: (...args) => JSON.stringify([...args]),
            $i18n: {
                locale: 'en-GB',
                messages: {
                    'en-GB': dictionary
                }
            }
        },
        provide: {
            repositoryFactory: {
                create: () => ({
                    search: () => Promise.resolve(responseMock)
                })
            },
            stateStyleDataProviderService: {},
            acl: {
                can: (identifier) => {
                    if (!identifier) { return true; }

                    return privileges.includes(identifier);
                }
            }
        },
        computed: {
            systemCurrencyISOCode() {
                return 'EUR';
            }
        }
    };

    if (orderSumToday !== null) {
        options.computed.orderSumToday = () => orderSumToday;
    }

    return shallowMount(Shopware.Component.build('sw-dashboard-index'), options);
}

describe('module/sw-dashboard/page/sw-dashboard-index', () => {
    let wrapper = createWrapper();

    beforeAll(() => {
        Shopware.State.registerModule('session', {
            state: {
                currentUser: null
            },
            mutations: {
                setCurrentUser(state, user) {
                    state.currentUser = user;
                }
            }
        });
        jest.useFakeTimers('modern');
    });

    beforeEach(async () => {
        wrapper = await createWrapper();
    });

    afterEach(() => {
        wrapper.destroy();
    });

    afterAll(() => {
        jest.useRealTimers();
    });

    it('should be a Vue.js component', async () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should not show the stats', async () => {
        const orderToday = wrapper.find('.sw-dashboard-index__intro-stats-today');
        const statisticsCount = wrapper.find('.sw-dashboard-index__statistics-count');
        const statisticsSum = wrapper.find('.sw-dashboard-index__statistics-sum');

        expect(orderToday.exists()).toBeFalsy();
        expect(statisticsCount.exists()).toBeFalsy();
        expect(statisticsSum.exists()).toBeFalsy();
    });

    it('should show the stats', async () => {
        wrapper = await createWrapper(['order.viewer']);
        await wrapper.vm.$nextTick();

        const orderToday = wrapper.find('.sw-dashboard-index__intro-stats-today');
        const statisticsCount = wrapper.find('.sw-dashboard-index__statistics-count');
        const statisticsSum = wrapper.find('.sw-dashboard-index__statistics-sum');

        expect(orderToday.exists()).toBeTruthy();
        expect(statisticsCount.exists()).toBeTruthy();
        expect(statisticsSum.exists()).toBeTruthy();
    });

    it('should return `null` as greetingName', async () => {
        expect(wrapper.text()).toContain('{"greetingName":null}');
    });

    it('should display users firstName', async () => {
        Shopware.State.commit('setCurrentUser', {
            firstName: 'userFirstName'
        });
        await wrapper.vm.$nextTick();

        expect(wrapper.text()).toContain('{"greetingName":"userFirstName"}');
    });

    it('should display `null` as greetingName, we only greet by firstName', async () => {
        Shopware.State.commit('setCurrentUser', {
            username: 'username'
        });
        await wrapper.vm.$nextTick();

        expect(wrapper.text()).toContain('{"greetingName":null}');
    });

    [
        {
            dateTime: new Date(2021, 4, 19, 4, 30, 30),
            expectedTimeSlot: '23h'
        },
        {
            dateTime: new Date(2021, 4, 19, 5, 30, 30),
            expectedTimeSlot: '5h'
        },
        {
            dateTime: new Date(2021, 4, 19, 10, 30, 30),
            expectedTimeSlot: '5h'
        },
        {
            dateTime: new Date(2021, 4, 19, 11, 30, 30),
            expectedTimeSlot: '11h'
        },
        {
            dateTime: new Date(2021, 4, 19, 14, 30, 30),
            expectedTimeSlot: '11h'
        },
        {
            dateTime: new Date(2021, 4, 19, 18, 30, 30),
            expectedTimeSlot: '18h'
        },
        {
            dateTime: new Date(2021, 4, 19, 22, 30, 30),
            expectedTimeSlot: '18h'
        },
        {
            dateTime: new Date(2021, 4, 19, 23, 30, 30),
            expectedTimeSlot: '23h'
        },
        {
            dateTime: new Date(2021, 4, 19, 0, 0, 0),
            expectedTimeSlot: '23h'
        }
    ].forEach(({ dateTime, expectedTimeSlot }) => {
        it(
            `should return datetime aware headline for daytime: ${dateTime.getHours()}h, expected slot: ${expectedTimeSlot}`,
            async () => {
                const greetingType = 'daytimeHeadline';
                /* as of today there are 4 timeslots: 23 - 4, 5 - 10, 11 - 17, 18 - 22 */
                /* the first param of `getGreetingTimeKey` must be ' headline' or 'welcomeText' */
                jest.setSystemTime(dateTime);
                expect(wrapper.vm.getGreetingTimeKey(greetingType))
                    .toContain(`sw-dashboard.introduction.${greetingType}.${expectedTimeSlot}`);
            }
        );
    });

    [
        {
            dateTime: new Date(2021, 4, 19, 4, 30, 30),
            expectedTimeSlot: '23h'
        },
        {
            dateTime: new Date(2021, 4, 19, 5, 30, 30),
            expectedTimeSlot: '5h'
        },
        {
            dateTime: new Date(2021, 4, 19, 10, 30, 30),
            expectedTimeSlot: '5h'
        },
        {
            dateTime: new Date(2021, 4, 19, 11, 30, 30),
            expectedTimeSlot: '11h'
        },
        {
            dateTime: new Date(2021, 4, 19, 14, 30, 30),
            expectedTimeSlot: '11h'
        },
        {
            dateTime: new Date(2021, 4, 19, 18, 30, 30),
            expectedTimeSlot: '18h'
        },
        {
            dateTime: new Date(2021, 4, 19, 22, 30, 30),
            expectedTimeSlot: '18h'
        },
        {
            dateTime: new Date(2021, 4, 19, 23, 30, 30),
            expectedTimeSlot: '23h'
        },
        {
            dateTime: new Date(2021, 4, 19, 0, 0, 0),
            expectedTimeSlot: '23h'
        }
    ].forEach(({ dateTime, expectedTimeSlot }) => {
        it(
            `should return datetime aware welcoming subline for daytime:\
            ${dateTime.getHours()}h, expected slot: ${expectedTimeSlot}`,
            async () => {
                const greetingType = 'daytimeWelcomeText';
                /* as of today there are 4 timeslots: 23 - 4, 5 - 10, 11 - 17, 18 - 22 */
                /* the first param of `getGreetingTimeKey` must be ' headline' or 'welcomeText' */
                jest.setSystemTime(dateTime);
                expect(wrapper.vm.getGreetingTimeKey(greetingType))
                    .toContain(`sw-dashboard.introduction.${greetingType}.${expectedTimeSlot}`);
            }
        );
    });

    it('should not exceed decimal places of two', async () => {
        wrapper = await createWrapper(['order.viewer'], 43383.13234554);
        await wrapper.vm.$nextTick();

        const todaysTotalSum =
            wrapper.find('.sw-dashboard-index__intro-stats-today-single-stat:nth-of-type(2) span:nth-of-type(2)').text();
        expect(todaysTotalSum).toBe('€43,383.13');
    });
});

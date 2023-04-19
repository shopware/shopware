import { shallowMount } from '@vue/test-utils';
import 'src/app/component/utils/sw-external-link';
import swDashboardIndex from 'src/module/sw-dashboard/page/sw-dashboard-index';
import dictionary from 'src/module/sw-dashboard/snippet/en-GB.json';

Shopware.Component.register('sw-dashboard-index', swDashboardIndex);

async function createWrapper(privileges = []) {
    return shallowMount(await Shopware.Component.build('sw-dashboard-index'), {
        stubs: {
            'sw-page': true,
            'sw-card-view': true,
            'sw-external-link': true,
            'sw-icon': true,
            'sw-dashboard-statistics': true,
            'sw-help-text': true,
        },
        mocks: {
            $tc: (...args) => JSON.stringify([...args]),
            $i18n: {
                locale: 'en-GB',
                messages: {
                    'en-GB': dictionary,
                },
            },
        },
        provide: {
            acl: {
                can: (identifier) => {
                    if (!identifier) { return true; }

                    return privileges.includes(identifier);
                },
            },
        },
    });
}

/**
 * @package merchant-services
 */
describe('module/sw-dashboard/page/sw-dashboard-index', () => {
    let wrapper;

    beforeAll(async () => {
        if (Shopware.State.get('session')) {
            Shopware.State.unregisterModule('session');
        }

        Shopware.State.registerModule('session', {
            state: {
                currentUser: null,
            },
            mutations: {
                setCurrentUser(state, user) {
                    state.currentUser = user;
                },
            },
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

    it('should return `null` as greetingName', async () => {
        expect(wrapper.text()).toContain('{"greetingName":null}');
    });

    it('should display users firstName', async () => {
        Shopware.State.commit('setCurrentUser', {
            firstName: 'userFirstName',
        });
        await wrapper.vm.$nextTick();

        expect(wrapper.text()).toContain('{"greetingName":"userFirstName"}');
    });

    it('should display `null` as greetingName, we only greet by firstName', async () => {
        Shopware.State.commit('setCurrentUser', {
            username: 'username',
        });
        await wrapper.vm.$nextTick();

        expect(wrapper.text()).toContain('{"greetingName":null}');
    });

    [
        {
            dateTime: new Date(2021, 4, 19, 4, 30, 30),
            expectedTimeSlot: '23h',
        },
        {
            dateTime: new Date(2021, 4, 19, 5, 30, 30),
            expectedTimeSlot: '5h',
        },
        {
            dateTime: new Date(2021, 4, 19, 10, 30, 30),
            expectedTimeSlot: '5h',
        },
        {
            dateTime: new Date(2021, 4, 19, 11, 30, 30),
            expectedTimeSlot: '11h',
        },
        {
            dateTime: new Date(2021, 4, 19, 14, 30, 30),
            expectedTimeSlot: '11h',
        },
        {
            dateTime: new Date(2021, 4, 19, 18, 30, 30),
            expectedTimeSlot: '18h',
        },
        {
            dateTime: new Date(2021, 4, 19, 22, 30, 30),
            expectedTimeSlot: '18h',
        },
        {
            dateTime: new Date(2021, 4, 19, 23, 30, 30),
            expectedTimeSlot: '23h',
        },
        {
            dateTime: new Date(2021, 4, 19, 0, 0, 0),
            expectedTimeSlot: '23h',
        },
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
            },
        );
    });

    [
        {
            dateTime: new Date(2021, 4, 19, 4, 30, 30),
            expectedTimeSlot: '23h',
        },
        {
            dateTime: new Date(2021, 4, 19, 5, 30, 30),
            expectedTimeSlot: '5h',
        },
        {
            dateTime: new Date(2021, 4, 19, 10, 30, 30),
            expectedTimeSlot: '5h',
        },
        {
            dateTime: new Date(2021, 4, 19, 11, 30, 30),
            expectedTimeSlot: '11h',
        },
        {
            dateTime: new Date(2021, 4, 19, 14, 30, 30),
            expectedTimeSlot: '11h',
        },
        {
            dateTime: new Date(2021, 4, 19, 18, 30, 30),
            expectedTimeSlot: '18h',
        },
        {
            dateTime: new Date(2021, 4, 19, 22, 30, 30),
            expectedTimeSlot: '18h',
        },
        {
            dateTime: new Date(2021, 4, 19, 23, 30, 30),
            expectedTimeSlot: '23h',
        },
        {
            dateTime: new Date(2021, 4, 19, 0, 0, 0),
            expectedTimeSlot: '23h',
        },
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
            },
        );
    });
});

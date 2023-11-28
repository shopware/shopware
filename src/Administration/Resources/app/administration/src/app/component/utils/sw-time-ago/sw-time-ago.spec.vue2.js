import { shallowMount, createLocalVue } from '@vue/test-utils';
import 'src/app/component/utils/sw-time-ago';

// mock Date.now() to 2025-06-24 15:00
Date.now = jest.fn(
    () => new Date(Date.UTC(2025, 5, 24, 15, 0)).valueOf(),
);


async function createWrapper(propsData = {}) {
    const localVue = createLocalVue();

    localVue.directive('tooltip', {
        bind(el, binding) {
            el.setAttribute('tooltip-message', binding.value.message);
            el.setAttribute('tooltip-disabled', binding.value.disabled);
        },
        inserted(el, binding) {
            el.setAttribute('tooltip-message', binding.value.message);
            el.setAttribute('tooltip-disabled', binding.value.disabled);
        },
        update(el, binding) {
            el.setAttribute('tooltip-message', binding.value.message);
            el.setAttribute('tooltip-disabled', binding.value.disabled);
        },
    });

    return shallowMount(await Shopware.Component.build('sw-time-ago'), {
        localVue,
        propsData: {
            ...propsData,
        },
        mocks: {
            $tc: (snippetPath, count, values) => snippetPath + count + JSON.stringify(values),
        },
    });
}

describe('src/app/component/utils/sw-time-ago', () => {
    /** @type Wrapper */
    let wrapper;

    beforeAll(async () => {});

    beforeEach(async () => {});

    afterEach(async () => {
        jest.useRealTimers();
        if (wrapper) await wrapper.destroy();
    });

    it('should update the time every minute', async () => {
        jest.useFakeTimers();

        Date.now = jest.fn(
            () => new Date(Date.UTC(2025, 5, 24, 15, 0)).valueOf(),
        );

        wrapper = await createWrapper({
            date: '2025-06-24T14:30:00.000+00:00',
        });

        expect(wrapper.vm.now).toBe(1750777200000);

        Date.now = jest.fn(
            () => new Date(Date.UTC(2025, 5, 24, 15, 1)).valueOf(),
        );

        jest.advanceTimersByTime(30000);

        await wrapper.vm.$nextTick();

        expect(wrapper.vm.now).toBe(1750777260000);
    });

    it('should clear intervals', async () => {
        jest.spyOn(global, 'clearInterval');

        wrapper = await createWrapper({ date: '2025-06-24T15:00:00.000+00:00' });

        expect(clearInterval).toHaveBeenCalledTimes(0);

        wrapper.destroy();

        expect(clearInterval).toHaveBeenCalledTimes(1);
        expect(clearInterval).toHaveBeenCalledWith(expect.any(Number));
    });

    it('should not clear intervals if not set', async () => {
        jest.spyOn(global, 'clearInterval');

        wrapper = await createWrapper({ date: '2025-06-24T15:00:00.000+00:00' });

        expect(clearInterval).toHaveBeenCalledTimes(0);

        wrapper.vm.interval = null;

        wrapper.destroy();

        expect(clearInterval).toHaveBeenCalledTimes(0);
    });

    describe('date property as string', () => {
        it('should be a Vue.JS component', async () => {
            wrapper = await createWrapper({ date: '2025-06-24T15:00:00.000+00:00' });

            expect(wrapper.vm).toBeTruthy();
        });

        describe('past dates', () => {
            it('should show the correct time for less than one minute', async () => {
                wrapper = await createWrapper({
                    date: '2025-06-24T15:00:00.000+00:00',
                });

                expect(wrapper.text()).toContain('global.sw-time-ago.justNow');
            });

            it('should show the correct time for less than one hour', async () => {
                wrapper = await createWrapper({
                    date: '2025-06-24T14:30:00.000+00:00',
                });

                expect(wrapper.text()).toContain('global.sw-time-ago.minutesAgo');
            });

            it('should show the correct time for today', async () => {
                wrapper = await createWrapper({
                    date: '2025-06-24T08:25:00.000+00:00',
                });

                expect(wrapper.text()).toContain('8:25');
            });

            it('should show the correct time for days more than one day ago', async () => {
                wrapper = await createWrapper({
                    date: '2025-06-16T15:00:00.000+00:00',
                });

                expect(wrapper.text()).toContain('16 June 2025 at 15:00');
            });

            it('should show a tooltip when day is today', async () => {
                wrapper = await createWrapper({
                    date: '2025-06-24T14:30:00.000+00:00',
                });

                expect(wrapper.find('span').attributes('tooltip-disabled')).toBe('false');
            });

            it('should not show a tooltip when day is not today', async () => {
                wrapper = await createWrapper({
                    date: '2025-06-21T14:30:00.000+00:00',
                });

                expect(wrapper.find('span').attributes('tooltip-disabled')).toBe('true');
            });
        });

        describe('future dates', () => {
            it('should show the correct time for less than one minute from now', async () => {
                wrapper = await createWrapper({
                    date: '2025-06-24T15:00:10.000+00:00',
                });

                expect(wrapper.text()).toContain('global.sw-time-ago.aboutNow');
            });

            it('should show the correct time for less than one hour from now', async () => {
                wrapper = await createWrapper({
                    date: '2025-06-24T15:30:00.000+00:00',
                });

                expect(wrapper.text()).toContain('global.sw-time-ago.minutesFromNow');
            });

            it('should show the correct time for today', async () => {
                wrapper = await createWrapper({
                    date: '2025-06-24T17:25:00.000+00:00',
                });

                expect(wrapper.text()).toContain('17:25');
            });

            it('should show the correct time for days more than one day from now', async () => {
                wrapper = await createWrapper({
                    date: '2025-06-30T15:00:00.000+00:00',
                });

                expect(wrapper.text()).toContain('30 June 2025 at 15:00');
            });

            it('should show a tooltip when day is today', async () => {
                wrapper = await createWrapper({
                    date: '2025-06-24T17:30:00.000+00:00',
                });

                expect(wrapper.find('span').attributes('tooltip-disabled')).toBe('false');
            });

            it('should not show a tooltip when day is not today', async () => {
                wrapper = await createWrapper({
                    date: '2025-06-27T15:00:00.000+00:00',
                });

                expect(wrapper.find('span').attributes('tooltip-disabled')).toBe('true');
            });
        });
    });

    describe('date property as object', () => {
        it('should be a Vue.JS component', async () => {
            wrapper = await createWrapper({ date: new Date('2025-06-24T15:00:00.000+00:00') });

            expect(wrapper.vm).toBeTruthy();
        });

        describe('past dates', () => {
            it('should show the correct time for less than one minute', async () => {
                wrapper = await createWrapper({
                    date: new Date('2025-06-24T15:00:00.000+00:00'),
                });

                expect(wrapper.text()).toContain('global.sw-time-ago.justNow');
            });

            it('should show the correct time for less than one hour', async () => {
                wrapper = await createWrapper({
                    date: new Date('2025-06-24T14:30:00.000+00:00'),
                });

                expect(wrapper.text()).toContain('global.sw-time-ago.minutesAgo');
            });

            it('should show the correct time for today', async () => {
                wrapper = await createWrapper({
                    date: new Date('2025-06-24T08:25:00.000+00:00'),
                });

                expect(wrapper.text()).toContain('8:25');
            });

            it('should show the correct time for days more than one day ago', async () => {
                wrapper = await createWrapper({
                    date: new Date('2025-06-16T15:00:00.000+00:00'),
                });

                expect(wrapper.text()).toContain('16 June 2025 at 15:00');
            });

            it('should show a tooltip when day is today', async () => {
                wrapper = await createWrapper({
                    date: new Date('2025-06-24T14:30:00.000+00:00'),
                });

                expect(wrapper.find('span').attributes('tooltip-disabled')).toBe('false');
            });

            it('should not show a tooltip when day is not today', async () => {
                wrapper = await createWrapper({
                    date: new Date('2025-06-21T14:30:00.000+00:00'),
                });

                expect(wrapper.find('span').attributes('tooltip-disabled')).toBe('true');
            });
        });

        describe('future dates', () => {
            it('should show the correct time for less than one minute from now', async () => {
                wrapper = await createWrapper({
                    date: new Date('2025-06-24T15:00:10.000+00:00'),
                });

                expect(wrapper.text()).toContain('global.sw-time-ago.aboutNow');
            });

            it('should show the correct time for less than one hour from now', async () => {
                wrapper = await createWrapper({
                    date: new Date('2025-06-24T15:30:00.000+00:00'),
                });

                expect(wrapper.text()).toContain('global.sw-time-ago.minutesFromNow');
            });

            it('should show the correct time for today', async () => {
                wrapper = await createWrapper({
                    date: new Date('2025-06-24T17:25:00.000+00:00'),
                });

                expect(wrapper.text()).toContain('17:25');
            });

            it('should show the correct time for days more than one day from now', async () => {
                wrapper = await createWrapper({
                    date: new Date('2025-06-30T15:00:00.000+00:00'),
                });

                expect(wrapper.text()).toContain('30 June 2025 at 15:00');
            });

            it('should show a tooltip when day is today', async () => {
                wrapper = await createWrapper({
                    date: new Date('2025-06-24T17:30:00.000+00:00'),
                });

                expect(wrapper.find('span').attributes('tooltip-disabled')).toBe('false');
            });

            it('should not show a tooltip when day is not today', async () => {
                wrapper = await createWrapper({
                    date: new Date('2025-06-27T15:00:00.000+00:00'),
                });

                expect(wrapper.find('span').attributes('tooltip-disabled')).toBe('true');
            });
        });
    });
});

/**
 * @sw-package framework
 */

import { mount } from '@vue/test-utils';
import { defineComponent } from 'vue';
import { registerTour } from 'src/app/service/tour.service';
import 'src/app/mixin/tour.mixin';

interface StepButton {
    classes: string;
    disabled?: boolean;
    action: () => void;
}

interface AddedStep {
    title?: string;
    buttons: StepButton[];
    beforeShowPromise: () => Promise<void>;
}

function createTourMock() {
    const handlers: Record<string, Array<() => void>> = {};
    const trigger = (event: string): void => handlers[event]?.forEach((handler) => handler());

    return {
        start: jest.fn(() => trigger('start')),
        cancel: jest.fn(() => trigger('cancel')),
        complete: jest.fn(() => trigger('complete')),
        back: jest.fn(),
        next: jest.fn(),
        isActive: jest.fn(() => false),
        addStep: jest.fn<void, [AddedStep]>(),
        on: jest.fn((event: string, handler: () => void) => {
            handlers[event] = [...(handlers[event] ?? []), handler];
        }),
        off: jest.fn((event: string, handler: () => void) => {
            handlers[event] = (handlers[event] ?? []).filter((registered) => registered !== handler);
        }),
    };
}

function createWrapper(moduleName: string, tour = createTourMock()) {
    const router = {
        resolve: jest.fn(() => ({ fullPath: '/jest/target' })),
        push: jest.fn(() => Promise.resolve()),
    };

    const wrapper = mount(
        defineComponent({
            template: '<div class="sw-jest-tour-target"></div>',
            mixins: [Shopware.Mixin.getByName('tour')],
        }),
        {
            attachTo: document.body,
            global: {
                mocks: {
                    $shepherd: jest.fn(() => tour),
                    $route: { fullPath: '/jest/start', meta: { $module: { name: moduleName } } },
                    $router: router,
                },
            },
        },
    );

    return { wrapper, tour, router };
}

function addedSteps(tour: ReturnType<typeof createTourMock>): AddedStep[] {
    return tour.addStep.mock.calls.map(([step]) => step);
}

function buttonClasses(step: AddedStep): string[] {
    return step.buttons.map((button) => button.classes.replace('sw-tour-button sw-tour-button--', ''));
}

describe('src/app/mixin/tour.mixin', () => {
    let mounted: ReturnType<typeof createWrapper> | null = null;
    const shortcutService = { stopEventListener: jest.fn(), startEventListener: jest.fn() };

    beforeAll(() => {
        Shopware.Service().register('shortcutService', () => shortcutService);

        registerTour('sw-jest-tour', {
            default: [
                { selector: '.sw-jest-tour-target', title: 'First' },
                { selector: '.sw-jest-tour-target', title: 'Second' },
            ],
            detail: [{ selector: '.sw-jest-tour-target', title: 'Detail' }],
        });
        registerTour('sw-jest-route-tour', {
            default: [
                { selector: '.sw-jest-tour-target', title: 'Here' },
                { route: { name: 'sw.jest.target' }, selector: '.sw-jest-tour-target', title: 'There' },
            ],
        });
    });

    afterEach(() => {
        mounted?.wrapper.unmount();
        mounted = null;
    });

    it('starts the default tour of the current module', () => {
        mounted = createWrapper('sw-jest-tour');

        mounted.wrapper.vm.startTour();

        expect(addedSteps(mounted.tour).map((step) => step.title)).toEqual([
            'First',
            'Second',
        ]);
        expect(mounted.tour.start).toHaveBeenCalledTimes(1);
    });

    it('starts the tour registered under the given key', () => {
        mounted = createWrapper('sw-jest-tour');

        mounted.wrapper.vm.startTour('detail');

        expect(addedSteps(mounted.tour).map((step) => step.title)).toEqual(['Detail']);
    });

    it('does not start a tour for a module without registered tours', () => {
        mounted = createWrapper('sw-jest-without-tour');

        mounted.wrapper.vm.startTour();

        expect(mounted.tour.start).not.toHaveBeenCalled();
    });

    it('disables going back on the first step and finishes on the last step', () => {
        mounted = createWrapper('sw-jest-tour');

        mounted.wrapper.vm.startTour();

        const [
            first,
            last,
        ] = addedSteps(mounted.tour);

        expect(buttonClasses(first)).toEqual([
            'prev',
            'next',
        ]);
        expect(first.buttons[0].disabled).toBe(true);
        expect(buttonClasses(last)).toEqual([
            'prev',
            'done',
        ]);
        expect(last.buttons[0].disabled).toBe(false);
    });

    it('navigates to the route of a step before showing it', async () => {
        mounted = createWrapper('sw-jest-route-tour');

        mounted.wrapper.vm.startTour();
        await addedSteps(mounted.tour)[1].beforeShowPromise();

        expect(mounted.router.push).toHaveBeenCalledWith({ name: 'sw.jest.target' });
    });

    it('returns to the route the tour started on', async () => {
        mounted = createWrapper('sw-jest-route-tour');

        mounted.wrapper.vm.startTour();

        const lastStep = addedSteps(mounted.tour)[1];
        const returnButton = lastStep.buttons.find((button) => button.classes.includes('--return'));
        returnButton?.action();
        await flushPromises();

        expect(mounted.tour.complete).toHaveBeenCalledTimes(1);
        expect(mounted.router.push).toHaveBeenCalledWith('/jest/start');
    });

    it('goes back to the previous route when stepping back from a routed step', async () => {
        mounted = createWrapper('sw-jest-route-tour');

        mounted.wrapper.vm.startTour();

        const routedStep = addedSteps(mounted.tour)[1];
        await routedStep.beforeShowPromise();
        routedStep.buttons[0].action();
        await flushPromises();

        expect(mounted.router.push).toHaveBeenLastCalledWith('/jest/start');
        expect(mounted.tour.back).toHaveBeenCalledTimes(1);
    });

    it('pauses the shortcuts while the tour is running', () => {
        shortcutService.stopEventListener.mockClear();
        shortcutService.startEventListener.mockClear();
        mounted = createWrapper('sw-jest-tour');

        mounted.wrapper.vm.startTour();

        expect(shortcutService.stopEventListener).toHaveBeenCalledTimes(1);
        expect(shortcutService.startEventListener).not.toHaveBeenCalled();

        mounted.tour.complete();

        expect(shortcutService.startEventListener).toHaveBeenCalledTimes(1);
    });

    it('cancels the running tour when the component is unmounted', () => {
        const { wrapper, tour } = createWrapper('sw-jest-tour');
        wrapper.vm.startTour();

        wrapper.unmount();

        expect(tour.cancel).toHaveBeenCalledTimes(1);
    });
});

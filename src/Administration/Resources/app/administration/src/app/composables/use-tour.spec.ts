/**
 * @sw-package framework
 */

import { createRouter, createWebHashHistory } from 'vue-router';
import type { Router } from 'vue-router';
import type { StepOptions, StepOptionsButton } from 'shepherd.js';
import useTour from 'src/app/composables/use-tour';
import { registerTour } from 'src/app/service/tour.service';

const tourState = {
    handlers: {} as Record<string, Array<() => void>>,
    steps: [] as StepOptions[],
};

function trigger(event: string): void {
    tourState.handlers[event]?.forEach((handler) => handler());
}

const tourMock = {
    start: jest.fn(() => trigger('start')),
    cancel: jest.fn(() => trigger('cancel')),
    complete: jest.fn(() => trigger('complete')),
    back: jest.fn(),
    next: jest.fn(),
    addStep: jest.fn((step: StepOptions) => {
        tourState.steps.push(step);
    }),
    on: jest.fn((event: string, handler: () => void) => {
        tourState.handlers[event] = [...(tourState.handlers[event] ?? []), handler];
    }),
    off: jest.fn((event: string, handler: () => void) => {
        tourState.handlers[event] = (tourState.handlers[event] ?? []).filter((registered) => registered !== handler);
    }),
};

jest.mock('shepherd.js', () => ({
    __esModule: true,
    default: {
        Tour: jest.fn(() => {
            tourState.handlers = {};
            tourState.steps = [];

            return tourMock;
        }),
    },
}));

const shortcutService = { stopEventListener: jest.fn(), startEventListener: jest.fn() };

async function createRouterOn(moduleName: string): Promise<Router> {
    const router = createRouter({
        history: createWebHashHistory(),
        routes: [
            {
                name: 'sw.jest.start',
                path: '/jest/start',
                component: { template: '<div></div>' },
                meta: { $module: { name: moduleName } },
            },
            {
                name: 'sw.jest.target',
                path: '/jest/target',
                component: { template: '<div></div>' },
            },
        ],
    });

    await router.push({ name: 'sw.jest.start' });
    Shopware.Application.view = { router } as typeof Shopware.Application.view;

    return router;
}

function buttonNames(step: StepOptions): string[] {
    return (step.buttons ?? []).map((button) => button.classes?.replace('sw-tour-button sw-tour-button--', '') ?? '');
}

function clickButton(step: StepOptions, name: string): void {
    const button = step.buttons?.find((candidate: StepOptionsButton) => candidate.classes?.endsWith(`--${name}`));

    button?.action?.call(tourMock as never);
}

describe('src/app/composables/use-tour', () => {
    const originalView = Shopware.Application.view;

    beforeAll(() => {
        // eslint-disable-next-line sw-deprecation-rules/private-feature-declarations -- registers a mock, not a new service
        Shopware.Service().register('shortcutService', () => shortcutService);
        registerTour('sw-jest-tour', {
            default: [
                { selector: '.sw-jest-first', title: 'First' },
                { selector: '.sw-jest-second', title: 'Second' },
            ],
            detail: [{ selector: '.sw-jest-detail', title: 'Detail' }],
        });
        registerTour('sw-jest-route-tour', {
            default: [
                { selector: '.sw-jest-here', title: 'Here' },
                { route: { name: 'sw.jest.target' }, selector: 'body', title: 'There' },
            ],
        });
    });

    beforeEach(() => {
        jest.clearAllMocks();
    });

    afterEach(() => {
        useTour().cancelTour();
        Shopware.Application.view = originalView;
    });

    it('starts the default tour of the current module', async () => {
        await createRouterOn('sw-jest-tour');

        useTour().startTour();

        expect(tourState.steps.map((step) => step.title)).toEqual([
            'First',
            'Second',
        ]);
        expect(tourState.steps[0].attachTo).toEqual({ element: '.sw-jest-first', on: 'bottom' });
        expect(tourMock.start).toHaveBeenCalledTimes(1);
    });

    it('starts the tour registered under the given key', async () => {
        await createRouterOn('sw-jest-tour');

        useTour().startTour('detail');

        expect(tourState.steps.map((step) => step.title)).toEqual(['Detail']);
    });

    it('does not start a tour for a module without registered tours', async () => {
        await createRouterOn('sw-jest-without-tour');

        useTour().startTour();

        expect(tourMock.start).not.toHaveBeenCalled();
    });

    it('cancels the running tour before it starts another one', async () => {
        await createRouterOn('sw-jest-tour');

        useTour().startTour();
        useTour().startTour('detail');

        expect(tourMock.cancel).toHaveBeenCalledTimes(1);
        expect(tourMock.start).toHaveBeenCalledTimes(2);
    });

    it('disables going back on the first step and finishes on the last step', async () => {
        await createRouterOn('sw-jest-tour');

        useTour().startTour();

        const [
            first,
            last,
        ] = tourState.steps;

        expect(buttonNames(first)).toEqual([
            'prev',
            'next',
        ]);
        expect(first.buttons?.[0].disabled).toBe(true);
        expect(buttonNames(last)).toEqual([
            'prev',
            'done',
        ]);
        expect(last.buttons?.[0].disabled).toBe(false);

        clickButton(first, 'next');
        clickButton(last, 'done');

        expect(tourMock.next).toHaveBeenCalledTimes(1);
        expect(tourMock.complete).toHaveBeenCalledTimes(1);
    });

    it('navigates to the route of a step before showing it', async () => {
        const router = await createRouterOn('sw-jest-route-tour');

        useTour().startTour();
        await tourState.steps[1].beforeShowPromise?.();

        expect(router.currentRoute.value.name).toBe('sw.jest.target');
    });

    it('goes back to the previous route when stepping back from a routed step', async () => {
        const router = await createRouterOn('sw-jest-route-tour');

        useTour().startTour();
        await tourState.steps[1].beforeShowPromise?.();
        clickButton(tourState.steps[1], 'prev');
        await flushPromises();

        expect(router.currentRoute.value.name).toBe('sw.jest.start');
        expect(tourMock.back).toHaveBeenCalledTimes(1);
    });

    it('returns to the route the tour started on', async () => {
        const router = await createRouterOn('sw-jest-route-tour');

        useTour().startTour();
        await tourState.steps[1].beforeShowPromise?.();
        clickButton(tourState.steps[1], 'return');
        await flushPromises();

        expect(tourMock.complete).toHaveBeenCalledTimes(1);
        expect(router.currentRoute.value.name).toBe('sw.jest.start');
    });

    it('pauses the shortcuts while the tour is running', async () => {
        await createRouterOn('sw-jest-tour');

        useTour().startTour();

        expect(shortcutService.stopEventListener).toHaveBeenCalledTimes(1);
        expect(shortcutService.startEventListener).not.toHaveBeenCalled();

        tourMock.complete();

        expect(shortcutService.startEventListener).toHaveBeenCalledTimes(1);
    });
});

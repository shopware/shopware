/**
 * @sw-package framework
 */

import { nextTick } from 'vue';
import type { Router } from 'vue-router';
import Shepherd from 'shepherd.js';
import type { StepOptions, StepOptionsButton, Tour } from 'shepherd.js';
import { getTour, type TourStepDefinition } from 'src/app/service/tour.service';

interface ShortcutServiceLike {
    stopEventListener?: () => void;
    startEventListener?: () => void;
}

interface TourRun {
    tour: Tour;
    router: Router;
    steps: TourStepDefinition[];
    startRoute: string;
    routeStack: string[];
}

const SCROLL_PADDING = 80;
const WAIT_INTERVAL_MS = 100;

let activeTour: Tour | null = null;

function pauseShortcuts(): void {
    const shortcutService = Shopware.Service('shortcutService') as ShortcutServiceLike | undefined;
    shortcutService?.stopEventListener?.();
}

function resumeShortcuts(): void {
    const shortcutService = Shopware.Service('shortcutService') as ShortcutServiceLike | undefined;
    shortcutService?.startEventListener?.();
}

function resolveModuleId(router: Router): string | null {
    const routeModule = router.currentRoute.value.meta?.$module as { name?: string } | undefined;
    const moduleName = routeModule?.name;
    if (!moduleName) {
        return null;
    }

    let moduleId: string = moduleName;
    Shopware.Module.getModuleRegistry().forEach((module, key) => {
        if (module?.manifest?.name === moduleName) {
            moduleId = key;
        }
    });

    return moduleId;
}

function resolveSteps(router: Router, key: string): TourStepDefinition[] {
    const moduleId = resolveModuleId(router);
    const tours = moduleId ? getTour(moduleId) : undefined;

    return tours?.[key] ?? tours?.default ?? [];
}

function waitForSelector(selector: string): Promise<void> {
    return new Promise<void>((resolve) => {
        const tick = (): void => {
            if (document.querySelector(selector)) {
                resolve();
                return;
            }

            setTimeout(tick, WAIT_INTERVAL_MS);
        };

        tick();
    });
}

function scrollIntoView(selector: string | undefined): void {
    const element = selector ? document.querySelector(selector) : null;
    if (!element) {
        return;
    }

    const rect = element.getBoundingClientRect();
    const inView =
        rect.top >= SCROLL_PADDING &&
        rect.left >= 0 &&
        rect.bottom <= window.innerHeight - SCROLL_PADDING &&
        rect.right <= window.innerWidth;

    if (!inView) {
        element.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
}

async function goBack(run: TourRun): Promise<void> {
    const target = run.routeStack.pop();
    if (target) {
        await run.router.push(target);
    }

    await run.tour.back();
}

function buildButtons(run: TourRun, index: number): StepOptionsButton[] {
    const isLast = index === run.steps.length - 1;
    const buttons: StepOptionsButton[] = [
        {
            text: '',
            classes: 'sw-tour-button sw-tour-button--prev',
            disabled: index === 0,
            action: (): void => {
                void goBack(run);
            },
        },
    ];

    if (!isLast) {
        buttons.push({
            text: '',
            classes: 'sw-tour-button sw-tour-button--next',
            action: (): void => {
                void run.tour.next();
            },
        });

        return buttons;
    }

    if (run.steps.some((step) => Boolean(step.route))) {
        buttons.push({
            text: '',
            classes: 'sw-tour-button sw-tour-button--return',
            action: (): void => {
                run.tour.complete();
                void run.router.push(run.startRoute);
            },
        });
    }

    buttons.push({
        text: '',
        classes: 'sw-tour-button sw-tour-button--done',
        action: (): void => {
            run.tour.complete();
        },
    });

    return buttons;
}

function buildStep(run: TourRun, step: TourStepDefinition, index: number): StepOptions {
    const { selector, waitFor, route, position, ...options } = step;
    const awaitedSelector = waitFor ?? (route ? selector : undefined);
    const showStep = step.when?.show;

    return {
        ...options,
        attachTo: { element: selector, on: position ?? 'bottom' },
        buttons: step.buttons ?? buildButtons(run, index),
        beforeShowPromise: async (): Promise<void> => {
            await step.beforeShowPromise?.();

            if (route) {
                const currentFullPath = run.router.currentRoute.value.fullPath;

                if (run.router.resolve(route).fullPath !== currentFullPath) {
                    run.routeStack.push(currentFullPath);
                    await run.router.push(route);
                    await nextTick();
                }
            }

            if (awaitedSelector) {
                await waitForSelector(awaitedSelector);
            }
        },
        when: {
            ...step.when,
            show(): void {
                showStep?.call(this);
                scrollIntoView(selector);
            },
        },
    };
}

function detachListeners(tour: Tour): void {
    tour.off('start', pauseShortcuts);
    tour.off('cancel', onTourEnd);
    tour.off('complete', onTourEnd);
}

function onTourEnd(): void {
    resumeShortcuts();

    if (activeTour) {
        detachListeners(activeTour);
    }

    activeTour = null;
}

function startTour(key = 'default'): void {
    const router = Shopware.Application.view?.router;
    const steps = router ? resolveSteps(router, key) : [];
    if (!router || steps.length === 0) {
        return;
    }

    if (activeTour) {
        // A replaced tour ends asynchronously and must not reset the one that starts now
        detachListeners(activeTour);
        void activeTour.cancel();
    }

    const tour = new Shepherd.Tour({
        useModalOverlay: true,
        defaultStepOptions: {
            scrollTo: false,
            cancelIcon: { enabled: true },
        },
    });
    const run: TourRun = {
        tour,
        router,
        steps,
        startRoute: router.currentRoute.value.fullPath,
        routeStack: [],
    };

    tour.on('start', pauseShortcuts);
    tour.on('cancel', onTourEnd);
    tour.on('complete', onTourEnd);
    steps.forEach((step, index) => tour.addStep(buildStep(run, step, index)));

    activeTour = tour;
    void tour.start();
}

function cancelTour(): void {
    void activeTour?.cancel();
}

/**
 * Starts the guided tours that modules register through `registerTour()`.
 * Only one tour runs at a time, and it survives the route changes of its own steps.
 *
 * @private
 */
export default function useTour(): {
    startTour: (key?: string) => void;
    cancelTour: () => void;
} {
    return { startTour, cancelTour };
}

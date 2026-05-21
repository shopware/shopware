/**
 * @sw-package framework
 */

import { defineComponent } from 'vue';
import { getTour, type TourStepDefinition } from 'src/app/service/tour.service';

interface ShepherdButton {
    text: string;
    classes?: string;
    disabled?: boolean;
    action?: (this: ShepherdTour) => void;
}

interface ShepherdTour {
    start(): void;
    cancel(): void;
    complete(): void;
    back(): void;
    next(): void;
    isActive(): boolean;
    addStep(step: Record<string, unknown>): unknown;
    on(event: string, handler: () => void): void;
    off(event: string, handler: () => void): void;
}

type ShepherdFactory = (options: Record<string, unknown>) => ShepherdTour;

interface ShortcutServiceLike {
    stopEventListener?: () => void;
    startEventListener?: () => void;
}

interface NormalizedAttachTo {
    element?: Element | string;
    on?: string;
}

/**
 * @private
 */
export default Shopware.Mixin.register(
    'tour',
    defineComponent({
        data() {
            return {
                tour: null as ShepherdTour | null,
                tourStartRoute: null as string | null,
                tourRouteStack: [] as string[],
                tourSteps: null as TourStepDefinition[] | null,
                tourKey: null as string | null,
                tourName: null as string | null,
                tourId: null as string | null,
                tourOptions: null as Record<string, unknown> | null,
            };
        },

        beforeUnmount() {
            this.detachTourEventListeners();
            this.tour?.cancel();
        },

        methods: {
            onTourStart(this: void): void {
                const shortcut = Shopware.Service('shortcutService') as ShortcutServiceLike | undefined;
                shortcut?.stopEventListener?.();
            },

            onTourEnd(this: void): void {
                const shortcut = Shopware.Service('shortcutService') as ShortcutServiceLike | undefined;
                shortcut?.startEventListener?.();
            },

            getTourOptions(): Record<string, unknown> {
                return {
                    useModalOverlay: true,
                    defaultStepOptions: {
                        scrollTo: false,
                        cancelIcon: {
                            enabled: true,
                        },
                        popperOptions: {
                            modifiers: [
                                {
                                    name: 'offset',
                                    options: {
                                        offset: [0, 16],
                                    },
                                },
                            ],
                        },
                    },
                    ...(this.tourOptions ?? {}),
                };
            },

            getTourSteps(): TourStepDefinition[] {
                return this.tourSteps ?? [];
            },

            loadTourStepsFromModule(): TourStepDefinition[] {
                if (this.tourSteps && Array.isArray(this.tourSteps) && this.tourSteps.length > 0) {
                    return this.tourSteps;
                }

                const routeModule = this.$route?.meta?.$module as { name?: string } | undefined;
                const moduleName = routeModule?.name;
                if (!moduleName) {
                    return [];
                }

                let moduleId: string = moduleName;
                const moduleRegistry = Shopware.Application.getContainer('factory')?.module?.getModuleRegistry?.();

                if (moduleRegistry && typeof moduleRegistry.forEach === 'function') {
                    moduleRegistry.forEach((module, key) => {
                        if (module?.manifest?.name === moduleName) {
                            moduleId = String(key);
                        }
                    });
                }

                const tourEntry = getTour(moduleId);
                if (!tourEntry) {
                    return [];
                }

                const tourKey = this.tourKey ?? this.tourName ?? this.tourId ?? 'default';
                const steps = tourEntry[tourKey] ?? tourEntry.default ?? [];

                if (!Array.isArray(steps)) {
                    return [];
                }

                this.tourSteps = steps;
                return this.tourSteps;
            },

            resolveTourElement(selector: string | Element | undefined): Element | string | null {
                if (!selector) {
                    return null;
                }

                if (typeof selector !== 'string') {
                    return selector;
                }

                return document.querySelector(selector);
            },

            waitForSelector(selector: string, intervalMs = 100): Promise<void> {
                return new Promise<void>((resolve) => {
                    const tick = (): void => {
                        if (document.querySelector(selector)) {
                            resolve();
                            return;
                        }

                        setTimeout(tick, intervalMs);
                    };

                    tick();
                });
            },

            getStepButtons(index: number, total: number): ShepherdButton[] {
                const isFirst = index === 0;
                const isLast = index === total - 1;
                const tourSteps = this.tourSteps ?? [];
                const hasRouteSteps = tourSteps.some((tourStep) => Boolean(tourStep.route));

                const buttons: ShepherdButton[] = [];
                buttons.push({
                    text: '',
                    classes: 'sw-tour-button sw-tour-button--prev',
                    disabled: isFirst,
                    action: (): void => {
                        void this.handleTourBack();
                    },
                });

                if (!isLast) {
                    buttons.push({
                        text: '',
                        classes: 'sw-tour-button sw-tour-button--next',
                        action(this: ShepherdTour): void {
                            this.next();
                        },
                    });

                    return buttons;
                }

                if (hasRouteSteps) {
                    buttons.push({
                        text: '',
                        classes: 'sw-tour-button sw-tour-button--return',
                        action: () => {
                            this.tour?.complete();
                            void this.returnToTourStart();
                        },
                    });
                }

                buttons.push({
                    text: '',
                    classes: 'sw-tour-button sw-tour-button--done',
                    action(this: ShepherdTour): void {
                        this.complete();
                    },
                });

                return buttons;
            },

            buildTourStep(
                step: TourStepDefinition,
                index: number,
                total: number,
            ): Record<string, unknown> | null {
                let normalizedAttachTo: NormalizedAttachTo | undefined = step.attachTo;

                if (!normalizedAttachTo) {
                    const selector = step.selector;
                    const element = typeof selector === 'string' ? null : this.resolveTourElement(selector);

                    if (!element && typeof selector !== 'string') {
                        return null;
                    }

                    normalizedAttachTo = {
                        element: typeof selector === 'string' ? selector : element ?? undefined,
                        on: step.position ?? 'bottom',
                    };
                }

                const existingWhen: Record<string, unknown> = step.when ?? {};
                const whenShow = existingWhen.show as (() => void) | undefined;
                const route = step.route;
                const beforeShowPromise = step.beforeShowPromise;
                const waitForSelector =
                    step.waitFor ??
                    (route && typeof step.selector === 'string' ? step.selector : undefined);

                return {
                    ...step,
                    attachTo: normalizedAttachTo,
                    buttons: step.buttons ?? this.getStepButtons(index, total),
                    beforeShowPromise: async (): Promise<void> => {
                        if (beforeShowPromise) {
                            await beforeShowPromise();
                        }

                        if (route && this.$router && this.$route) {
                            const currentFullPath = this.$route.fullPath;
                            const targetFullPath = this.$router.resolve(route).fullPath;

                            if (targetFullPath !== currentFullPath) {
                                this.tourRouteStack.push(currentFullPath);
                                await this.$router.push(route);
                                await this.$nextTick();
                            }
                        }

                        if (waitForSelector) {
                            await this.waitForSelector(waitForSelector);
                        }
                    },
                    when: {
                        ...existingWhen,
                        show: (): void => {
                            if (whenShow) {
                                whenShow();
                            }

                            const elementRef = normalizedAttachTo?.element;
                            const element =
                                typeof elementRef === 'string'
                                    ? document.querySelector(elementRef)
                                    : elementRef;
                            if (!element) {
                                return;
                            }

                            const padding = 80;
                            const rect = element.getBoundingClientRect();
                            const inView =
                                rect.top >= padding &&
                                rect.left >= 0 &&
                                rect.bottom <= window.innerHeight - padding &&
                                rect.right <= window.innerWidth;

                            if (!inView) {
                                element.scrollIntoView({ behavior: 'smooth', block: 'center' });
                            }
                        },
                    },
                };
            },

            attachTourEventListeners(): void {
                if (!this.tour) {
                    return;
                }

                this.tour.on('start', this.onTourStart);
                this.tour.on('cancel', this.onTourEnd);
                this.tour.on('complete', this.onTourEnd);
            },

            detachTourEventListeners(): void {
                if (!this.tour) {
                    return;
                }

                this.tour.off('start', this.onTourStart);
                this.tour.off('cancel', this.onTourEnd);
                this.tour.off('complete', this.onTourEnd);
            },

            createTour(): ShepherdTour | null {
                if (this.tour) {
                    return this.tour;
                }

                let steps = this.getTourSteps();
                if (steps.length === 0) {
                    steps = this.loadTourStepsFromModule();
                }
                if (steps.length === 0) {
                    return null;
                }

                const shepherd = (this as unknown as { $shepherd?: ShepherdFactory }).$shepherd;
                if (!shepherd) {
                    return null;
                }

                const tour = shepherd(this.getTourOptions());
                this.tour = tour;

                this.attachTourEventListeners();

                steps.forEach((step, index) => {
                    const normalizedStep = this.buildTourStep(step, index, steps.length);

                    if (!normalizedStep) {
                        return;
                    }

                    tour.addStep(normalizedStep);
                });

                return tour;
            },

            startTour(key: string = 'default'): void {
                this.detachTourEventListeners();
                this.tourKey = key;
                this.tourSteps = null;
                this.tour = null;
                this.tourRouteStack = [];
                this.tourStartRoute = this.$route?.fullPath ?? null;

                const tour = this.createTour();
                if (!tour) {
                    return;
                }

                if (tour.isActive()) {
                    tour.cancel();
                }

                tour.start();
            },

            async returnToTourStart(): Promise<void> {
                if (!this.tourStartRoute || !this.$router) {
                    return;
                }

                const target = this.tourStartRoute;
                this.tourStartRoute = null;

                await this.$router.push(target);
            },

            async handleTourBack(): Promise<void> {
                if (this.tourRouteStack.length > 0 && this.$router) {
                    const target = this.tourRouteStack.pop();

                    if (target) {
                        await this.$router.push(target);
                    }
                }

                this.tour?.back();
            },
        },
    }),
);

/**
 * @sw-package framework
 */

import { defineComponent } from 'vue';

const tourModules = import.meta.glob('/src/module/**/*.tour.{js,ts}');

/**
 * @private
 */
export default Shopware.Mixin.register(
    'tour',
    defineComponent({
        data() {
            return {
                // eslint-disable-next-line @typescript-eslint/no-explicit-any
                tour: null,
                tourStartRoute: null as null | string,
                tourRouteStack: [] as string[],
            };
        },

        beforeUnmount() {
            if (this.tour?.cancel) {
                this.tour.cancel();
            }
        },

        methods: {
            disableShortcuts() {
                Shopware.Service('shortcutService')?.stopEventListener?.();
            },

            enableShortcuts() {
                Shopware.Service('shortcutService')?.startEventListener?.();
            },

            getTourOptions(): {
                useModalOverlay: boolean;
                defaultStepOptions: Record<string, unknown>
            } & Record<string, unknown> {
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

            getTourSteps() {
                return this.tourSteps ?? [];
            },

            async loadTourStepsFromModule() {
                if (this.tourSteps && Array.isArray(this.tourSteps) && this.tourSteps.length > 0) {
                    return this.tourSteps;
                }

                const moduleName = this.$route?.meta?.$module?.name;
                if (!moduleName) {
                    return [];
                }

                let moduleId = moduleName;
                const moduleRegistry = Shopware.Application.getContainer('factory')?.module?.getModuleRegistry?.();

                if (moduleRegistry && typeof moduleRegistry.forEach === 'function') {
                    moduleRegistry.forEach((module, key) => {
                        if (module?.manifest?.name === moduleName) {
                            moduleId = key;
                        }
                    });
                }

                const tsPath = `/src/module/${moduleId}/${moduleId}.tour.ts`;
                const jsPath = `/src/module/${moduleId}/${moduleId}.tour.js`;
                const loader = tourModules[tsPath] ?? tourModules[jsPath];

                if (!loader) {
                    return [];
                }

                // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment
                const mod = await loader();
                // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access
                const steps = mod?.default ?? [];

                if (Array.isArray(steps)) {
                    this.tourSteps = steps as unknown[];
                    return this.tourSteps;
                }

                const tourKey = (this.tourKey ?? this.tourName ?? this.tourId) as string | undefined;
                if (tourKey && steps && typeof steps === 'object') {
                    // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access
                    const keyedSteps = (steps as { [key: string]: unknown })[tourKey];
                    if (Array.isArray(keyedSteps)) {
                        this.tourSteps = keyedSteps;
                        return this.tourSteps;
                    }
                }

                return [];
            },

            resolveTourElement(selector: string | Element) {
                if (!selector) {
                    return null;
                }

                if (typeof selector !== 'string') {
                    return selector;
                }

                return document.querySelector(selector);
            },

            waitForSelector(selector: string, intervalMs = 100) {
                return new Promise<void>((resolve) => {
                    const tick = () => {
                        if (document.querySelector(selector)) {
                            resolve();
                            return;
                        }

                        setTimeout(tick, intervalMs);
                    };

                    tick();
                });
            },

            getStepButtons(index: number, total: number) {
                const isFirst = index === 0;
                const isLast = index === total - 1;
                const hasRouteSteps = Array.isArray(this.tourSteps)
                    ? this.tourSteps.some((tourStep) => Boolean(tourStep?.route))
                    : false;

                const buttons = [];
                buttons.push({
                    text: '',
                    classes: `sw-tour-button sw-tour-button--prev`,
                    disabled: isFirst,
                    action: () => this.handleTourBack(),
                });

                if (!isLast) {
                    buttons.push({
                        text: '',
                        classes: 'sw-tour-button sw-tour-button--next',
                        action() {
                            return this.next();
                        },
                    });

                    return buttons;
                }

                if (hasRouteSteps) {
                    buttons.push({
                        text: '',
                        classes: 'sw-tour-button sw-tour-button--return',
                        action: () => {
                            this.tour?.complete?.();
                            this.returnToTourStart();
                        },
                    });
                }

                buttons.push({
                    text: '',
                    classes: 'sw-tour-button sw-tour-button--done',
                    action() {
                        return this.complete();
                    },
                });

                return buttons;
            },

            buildTourStep(step: Record<string, unknown>, index: number, total: number) {
                const attachTo = step.attachTo as { element?: Element; on?: string } | undefined;
                let normalizedAttachTo = attachTo;

                if (!normalizedAttachTo) {
                    const selector = step.selector as string | Element | undefined;
                    const element = typeof selector === 'string' ? null : this.resolveTourElement(selector ?? '');

                    if (!element && typeof selector !== 'string') {
                        return null;
                    }

                    normalizedAttachTo = {
                        element: typeof selector === 'string' ? selector : element,
                        on: (step.position as string | undefined) ?? 'bottom',
                    };
                }

                const existingWhen = (step.when as Record<string, unknown>) ?? {};
                const whenShow = existingWhen.show as (() => void) | undefined;
                const route = step.route as string | Record<string, unknown> | undefined;
                const beforeShowPromise = step.beforeShowPromise as (() => Promise<void>) | undefined;
                const waitForSelector =
                    (step.waitFor as string | undefined) ??
                    (route && typeof step.selector === 'string' ? step.selector : undefined);

                return {
                    ...step,
                    attachTo: normalizedAttachTo,
                    buttons: (step.buttons as unknown[]) ?? this.getStepButtons(index, total),
                    beforeShowPromise: async () => {
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
                        show: () => {
                            if (whenShow) {
                                whenShow();
                            }

                            const element =
                                typeof normalizedAttachTo?.element === 'string'
                                    ? document.querySelector(normalizedAttachTo.element)
                                    : (normalizedAttachTo?.element as Element | undefined);
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

            async createTour() {
                if (this.tour) {
                    return;
                }

                let steps = this.getTourSteps();
                if (!Array.isArray(steps) || steps.length === 0) {
                    steps = await this.loadTourStepsFromModule();
                }
                if (!Array.isArray(steps) || steps.length === 0) {
                    return;
                }

                // eslint-disable-next-line @typescript-eslint/no-unsafe-call
                this.tour = this.$shepherd(this.getTourOptions());

                if (!this.tour) {
                    return;
                }

                this.tour.on('start', () => this.disableShortcuts());
                this.tour.on('cancel', () => this.enableShortcuts());
                this.tour.on('complete', () => this.enableShortcuts());

                steps.forEach((step, index) => {
                    const normalizedStep = this.buildTourStep(step, index, steps.length);

                    if (!normalizedStep) {
                        return;
                    }

                    // eslint-disable-next-line @typescript-eslint/no-unsafe-call
                    this.tour.addStep(normalizedStep);
                });
            },

            async startTour(key: string = 'default') {
                this.tourKey = key;
                this.tourSteps = null;
                this.tour = null;
                this.tourRouteStack = [];
                this.tourStartRoute = this.$route?.fullPath ?? null;

                await this.createTour();

                if (this.tour?.isActive?.()) {
                    this.tour.cancel();
                }

                this.tour?.start?.();
            },

            async returnToTourStart() {
                if (!this.tourStartRoute || !this.$router) {
                    return;
                }

                const target = this.tourStartRoute;
                this.tourStartRoute = null;

                await this.$router.push(target);
            },

            async handleTourBack() {
                if (this.tourRouteStack.length > 0 && this.$router) {
                    const target = this.tourRouteStack.pop();

                    if (target) {
                        await this.$router.push(target);
                    }
                }

                this.tour?.back?.();
            },
        },
    }),
);

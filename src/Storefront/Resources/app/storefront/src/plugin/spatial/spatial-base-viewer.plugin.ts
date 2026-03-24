// @ts-ignore
import Plugin from 'src/plugin-system/plugin.class';
// @ts-ignore
import type NativeEventEmitter from 'src/helper/emitter.helper';
import { loadDIVE } from './utils/spatial-dive-load-util';

/**
 * @package innovation
 *
 * @experimental stableVersion:v6.8.0 feature:SPATIAL_BASES
 */
// @ts-ignore
export default class SpatialBaseViewerPlugin extends Plugin {

    protected rendering = false;

    public canvas: HTMLCanvasElement | undefined;

    public ready = false;
    $emitter: NativeEventEmitter;

    public options!: {
        modelUrl: string;
        sliderPosition: number;
    };

    // eslint-disable-next-line @typescript-eslint/consistent-type-imports
    protected dive: import('@shopware-ag/dive/quickview').QuickView | undefined;

    /**
     * initialize plugin
     */
    public async init() {
        await loadDIVE();

        await this.initViewer();
    }

    /**
     * initialize the viewer
     * @param force - Will reinitialize the viewer entirely. Otherwise, only the canvas and renderer will be reinitialized.
     */
    public async initViewer() {
        this.setReady(false);
        // @ts-ignore
        this.canvas = this.el as HTMLCanvasElement;
        this.canvas.tabIndex = 0;

        if (this.dive == undefined) {
            this.dive = await window.DIVEQuickViewPlugin.QuickView(this.options.modelUrl, { autoStart: false, canvas: this.canvas });

            if (this.dive.model.animations.length > 0) {
                // instantiate animation system
                const animSystem = new window.DIVEAnimationPlugin.AnimationSystem();
                await animSystem.fromClips(this.dive.model, this.dive.model.animations);
                this.dive.clock.addTicker(animSystem);

                // create animator
                const animator = await animSystem.fromClips(this.dive.model, this.dive.model.animations);
                animator.loop = 'repeat';

                // automatically play the first animation
                animator.play();

                // container
                const animButtonContainer = this.canvas.parentElement?.querySelector('.spatial-anim-button-container') as HTMLElement | null;
                if (!animButtonContainer) {
                    return;
                }

                // button
                const animButton = animButtonContainer.querySelector('.spatial-anim-button');
                if (!animButton) {
                    return;
                }

                animButton.addEventListener('click', () => {
                    if (animator.state === 'playing') {
                        animator.pause();
                        animButtonContainer.classList.remove('spatial-anim-play');
                    } else {
                        animator.play();
                        animButtonContainer.classList.add('spatial-anim-play');
                    }
                });

                const originalUpdate = animator.update.bind(animator);
                animator.update = (deltaTime: number) => {
                    originalUpdate(deltaTime);
                    const progress = animator.duration > 0 ? animator.time / animator.duration : 0;
                    animButtonContainer.style.setProperty('--progress', String(progress));
                };

                // show button
                animButtonContainer.classList.add('spatial-anim-play');
                animButtonContainer.classList.remove('hidden');
            }
        }

        // @ts-ignore
        this.$emitter.publish('Viewer/initViewer');
    }

    /**
     * Start rendering loop
     */
    public startRendering() {
        // Prevent multiple render loops
        if (this.rendering) {
            return;
        }

        // start render loop
        this.rendering = true;
        this.dive?.start();

        // Add classes to canvas parent
        this.canvas?.parentElement?.classList.add('spatial-canvas-rendering');

        if (this.ready) {
            this.canvas?.parentElement?.classList.add('spatial-canvas-display');
        }

        // Publish events
        // @ts-ignore
        this.$emitter.publish('Viewer/startRendering');
    }

    /**
     * Stop rendering loop
     */
    public stopRendering() {
        // stop render loop
        this.rendering = false;

        this.dive?.stop();

        // Remove classes from canvas parent
        this.canvas?.parentElement?.classList.remove('spatial-canvas-rendering');

        // Publish events
        // @ts-ignore
        this.$emitter.publish('Viewer/stopRendering');
    }

    public setReady(ready: boolean) {
        if (this.ready === ready) {
            return;
        }
        this.ready = ready;
        this.onReady(ready);
    }

    protected onReady(state: boolean) {
        if (this.canvas == undefined) {
            return;
        }
        if (state) {
            // @ts-ignore
            this.$emitter.publish('Viewer/ready');
            this.canvas.parentElement?.classList.add('spatial-canvas-ready');
            if (this.rendering) {
                this.canvas.parentElement?.classList.add('spatial-canvas-display');
            }
        } else {
            // @ts-ignore
            this.$emitter.publish('Viewer/notReady');
            this.canvas.parentElement?.classList.remove('spatial-canvas-ready');
            this.canvas.parentElement?.classList.remove('spatial-canvas-display');
        }
    }
}

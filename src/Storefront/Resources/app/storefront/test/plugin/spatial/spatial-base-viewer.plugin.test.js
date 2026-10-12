import SpatialBaseViewerPlugin from 'src/plugin/spatial/spatial-base-viewer.plugin';

jest.mock('src/plugin/spatial/utils/spatial-dive-load-util');

/**
 * @package innovation
 */
describe('SpatialBaseViewerPlugin tests', () => {
    let spatialBaseViewerPlugin;
    let parentDiv;
    let parentDivClassListAddSpy;
    let parentDivClassListRemoveSpy;
    let emitterPublishSpy;
    const mockDive = {
        startAsync: jest.fn(),
        stop: jest.fn(),
        model: {
            animations: [],
        },
        clock: {
            addTicker: jest.fn(),
        },
    };
    window.DIVEQuickViewPlugin = {
        QuickView: jest.fn().mockResolvedValue(mockDive),
    };

    beforeEach(() => {
        mockDive.model.animations = [];

        document.body.innerHTML =  `
            <div id="parentDiv">
                <canvas id="canvasEl"></canvas>
            </div>
        `;
        parentDiv = document.getElementById('parentDiv');

        jest.useFakeTimers();

        window.DIVEQuickViewPlugin = {
            QuickView: jest.fn().mockResolvedValue(mockDive),
        };

        spatialBaseViewerPlugin = new SpatialBaseViewerPlugin(document.getElementById('canvasEl'));
        parentDivClassListAddSpy = jest.spyOn(parentDiv.classList, 'add');
        parentDivClassListRemoveSpy = jest.spyOn(parentDiv.classList, 'remove');
        emitterPublishSpy = jest.spyOn(spatialBaseViewerPlugin.$emitter, 'publish');
    });

    afterEach(() => {
        jest.useRealTimers();
    });

    test('plugin initializes', () => {
        expect(typeof spatialBaseViewerPlugin).toBe('object');
    });

    test('setReady no action if already set ready property', () => {
        spatialBaseViewerPlugin.ready = false;

        spatialBaseViewerPlugin.setReady(true);

        expect(spatialBaseViewerPlugin.ready).toBe(true);
    });

    test('setReady makes no action if already set the same value as in the parameter', () => {
        spatialBaseViewerPlugin.ready = true;

        spatialBaseViewerPlugin.setReady(true);

        expect(parentDivClassListAddSpy).not.toHaveBeenCalled();
    });

    test('setReady with parameter `state` in true will add class `spatial-canvas-ready`', () => {
        spatialBaseViewerPlugin.ready = false;
        spatialBaseViewerPlugin.rendering = false;

        spatialBaseViewerPlugin.setReady(true);

        expect(parentDivClassListAddSpy).toHaveBeenCalledTimes(1);
        expect(parentDivClassListAddSpy).toHaveBeenCalledWith('spatial-canvas-ready');
    });

    test('setReady with parameter `state` in true and property `rendering` is true will add class `spatial-canvas-display`', () => {
        spatialBaseViewerPlugin.ready = false;
        spatialBaseViewerPlugin.rendering = true;

        spatialBaseViewerPlugin.setReady(true);

        expect(parentDivClassListAddSpy).toHaveBeenCalledTimes(2);
        expect(parentDivClassListAddSpy).toHaveBeenLastCalledWith('spatial-canvas-display');
    });

    test('setReady with parameter `state` in false will remove classes `spatial-canvas-ready` and `spatial-canvas-display`', () => {
        spatialBaseViewerPlugin.ready = true;

        spatialBaseViewerPlugin.setReady(false);

        expect(parentDivClassListRemoveSpy).toHaveBeenCalledTimes(2);
        expect(parentDivClassListRemoveSpy).toHaveBeenLastCalledWith('spatial-canvas-display');
    });

    test('onReady with undefined `canvas` will makes no actions', () => {
        spatialBaseViewerPlugin.ready = true;
        spatialBaseViewerPlugin.canvas = undefined;
        emitterPublishSpy.mockClear();

        spatialBaseViewerPlugin.setReady(false);

        expect(emitterPublishSpy).not.toHaveBeenCalled();
    });

    test('startRendering if already rendered will makes no actions', async () => {
        spatialBaseViewerPlugin.rendering = true;

        await spatialBaseViewerPlugin.startRendering();

        expect(mockDive.startAsync).not.toHaveBeenCalled();
    });

    test('startRendering with `ready` property in false will not add the class `spatial-canvas-display`', async () => {
        spatialBaseViewerPlugin.rendering = false;
        spatialBaseViewerPlugin.ready = false;

        await spatialBaseViewerPlugin.startRendering();

        expect(mockDive.startAsync).toHaveBeenCalled();
        expect(parentDivClassListAddSpy).toHaveBeenCalledTimes(1);
        expect(parentDivClassListAddSpy).toHaveBeenCalledWith('spatial-canvas-rendering');
        expect(emitterPublishSpy).toHaveBeenCalled();
    });

    test('startRendering with `ready` property in true will add the class `spatial-canvas-display`', async () => {
        spatialBaseViewerPlugin.rendering = false;
        spatialBaseViewerPlugin.ready = true;

        await spatialBaseViewerPlugin.startRendering();

        expect(parentDivClassListAddSpy).toHaveBeenCalledTimes(2);
        expect(parentDivClassListAddSpy).toHaveBeenCalledWith('spatial-canvas-display');
    });

    test('stopRendering will stop rendering loop', () => {
        spatialBaseViewerPlugin.rendering = true;

        spatialBaseViewerPlugin.stopRendering();

        expect(spatialBaseViewerPlugin.rendering).toBe(false);
        expect(mockDive.stop).toHaveBeenCalledTimes(1);
        expect(parentDivClassListRemoveSpy).toHaveBeenCalledWith('spatial-canvas-rendering');
        expect(emitterPublishSpy).toHaveBeenCalledWith('Viewer/stopRendering');
    });

    test('stopRendering makes no actions if not rendering', () => {
        spatialBaseViewerPlugin.rendering = false;

        spatialBaseViewerPlugin.stopRendering();

        expect(mockDive.stop).not.toHaveBeenCalled();
        expect(parentDivClassListRemoveSpy).not.toHaveBeenCalled();
        expect(emitterPublishSpy).not.toHaveBeenCalledWith('Viewer/stopRendering');
    });

    test('startRendering gives up when the viewer was stopped while the engine started', async () => {
        let releaseStart;
        mockDive.startAsync.mockReturnValueOnce(new Promise((resolve) => {
            releaseStart = resolve;
        }));

        spatialBaseViewerPlugin.rendering = false;
        spatialBaseViewerPlugin.ready = true;

        const started = spatialBaseViewerPlugin.startRendering();

        spatialBaseViewerPlugin.stopRendering();
        parentDivClassListAddSpy.mockClear();
        emitterPublishSpy.mockClear();

        releaseStart();
        await started;

        expect(spatialBaseViewerPlugin.rendering).toBe(false);
        expect(parentDivClassListAddSpy).not.toHaveBeenCalled();
        expect(emitterPublishSpy).not.toHaveBeenCalledWith('Viewer/startRendering');
    });

    test('startRendering restarts a viewer that was stopped before', async () => {
        spatialBaseViewerPlugin.rendering = false;
        spatialBaseViewerPlugin.ready = true;

        await spatialBaseViewerPlugin.startRendering();
        spatialBaseViewerPlugin.stopRendering();

        mockDive.startAsync.mockClear();
        parentDivClassListAddSpy.mockClear();
        emitterPublishSpy.mockClear();

        await spatialBaseViewerPlugin.startRendering();

        expect(spatialBaseViewerPlugin.rendering).toBe(true);
        expect(mockDive.startAsync).toHaveBeenCalledTimes(1);
        expect(parentDivClassListAddSpy).toHaveBeenCalledWith('spatial-canvas-rendering');
        expect(parentDivClassListAddSpy).toHaveBeenCalledWith('spatial-canvas-display');
        expect(emitterPublishSpy).toHaveBeenCalledWith('Viewer/startRendering');
    });
});

describe('SpatialBaseViewerPlugin animation tests', () => {
    let mockAnimator;
    let mockAnimSystem;

    function createMockAnimator() {
        return {
            loop: null,
            state: 'playing',
            time: 0,
            duration: 10,
            play: jest.fn(),
            pause: jest.fn(),
            resume: jest.fn(),
            update: jest.fn(),
        };
    }

    function createMockAnimSystem() {
        return {
            fromClips: jest.fn().mockResolvedValue(mockAnimator),
        };
    }

    function buildDOM({ withAnimContainer = true, withButton = true, withCircle = true, withSwitch = true, withSelect = true } = {}) {
        let animContainerHTML = '';

        if (withAnimContainer) {
            const buttonHTML = withButton ? `
                <button class="spatial-anim-button visually-hidden">
                    ${withCircle ? '<svg class="spatial-anim-button-circle"><circle></circle></svg>' : ''}
                </button>
            ` : '';

            const switchHTML = withSwitch ? `
                <span class="spatial-anim-switch-container visually-hidden">
                    ${withSelect ? '<select class="spatial-anim-switch"></select>' : ''}
                </span>
            ` : '';

            animContainerHTML = `<div class="spatial-anim-container">${buttonHTML}${switchHTML}</div>`;
        }

        document.body.innerHTML = `
            <div id="parentDiv">
                <canvas id="canvasEl"></canvas>
                ${animContainerHTML}
            </div>
        `;
    }

    beforeEach(() => {
        jest.useFakeTimers();
        mockAnimator = createMockAnimator();
        mockAnimSystem = createMockAnimSystem();

        window.DIVEAnimationPlugin = {
            AnimationSystem: jest.fn().mockReturnValue(mockAnimSystem),
        };

        window.DIVEQuickViewPlugin = {
            QuickView: jest.fn().mockResolvedValue({
                model: { animations: [] },
                clock: { addTicker: jest.fn() },
            }),
        };
    });

    afterEach(() => {
        jest.useRealTimers();
    });

    function createPlugin() {
        const initSpy = jest.spyOn(SpatialBaseViewerPlugin.prototype, 'init').mockResolvedValue(undefined);
        const plugin = new SpatialBaseViewerPlugin(document.getElementById('canvasEl'));
        initSpy.mockRestore();
        return plugin;
    }

    test('initViewer does not set up animations when model has no animations', async () => {
        buildDOM();

        const diveWithNoAnims = {
            model: { animations: [] },
            clock: { addTicker: jest.fn() },
        };
        window.DIVEQuickViewPlugin.QuickView.mockResolvedValue(diveWithNoAnims);

        const plugin = createPlugin();
        await plugin.initViewer();

        expect(window.DIVEAnimationPlugin.AnimationSystem).not.toHaveBeenCalled();
    });

    test('initViewer sets up animation system when model has animations', async () => {
        buildDOM();

        const animations = [{ name: 'Walk' }];
        const diveWithAnims = {
            model: { animations },
            clock: { addTicker: jest.fn() },
        };
        window.DIVEQuickViewPlugin.QuickView.mockResolvedValue(diveWithAnims);

        const plugin = createPlugin();
        await plugin.initViewer();

        expect(window.DIVEAnimationPlugin.AnimationSystem).toHaveBeenCalled();
        expect(mockAnimSystem.fromClips).toHaveBeenCalledWith(diveWithAnims.model, animations);
        expect(diveWithAnims.clock.addTicker).toHaveBeenCalledWith(mockAnimSystem);
        expect(mockAnimator.loop).toBe('repeat');
        expect(mockAnimator.play).toHaveBeenCalled();
    });

    test('initViewer shows animation button and removes visually-hidden class', async () => {
        buildDOM();

        const animations = [{ name: 'Walk' }];
        const diveWithAnims = {
            model: { animations },
            clock: { addTicker: jest.fn() },
        };
        window.DIVEQuickViewPlugin.QuickView.mockResolvedValue(diveWithAnims);

        const plugin = createPlugin();
        await plugin.initViewer();

        const animButton = document.querySelector('.spatial-anim-button');
        expect(animButton.classList.contains('visually-hidden')).toBe(false);
        expect(animButton.classList.contains('spatial-anim-play')).toBe(true);
    });

    test('animation button click pauses when playing', async () => {
        buildDOM();

        const animations = [{ name: 'Walk' }];
        const diveWithAnims = {
            model: { animations },
            clock: { addTicker: jest.fn() },
        };
        window.DIVEQuickViewPlugin.QuickView.mockResolvedValue(diveWithAnims);

        const plugin = createPlugin();
        await plugin.initViewer();

        const animButton = document.querySelector('.spatial-anim-button');
        mockAnimator.state = 'playing';
        animButton.click();

        expect(mockAnimator.pause).toHaveBeenCalled();
        expect(animButton.classList.contains('spatial-anim-play')).toBe(false);
    });

    test('animation button click resumes when paused', async () => {
        buildDOM();

        const animations = [{ name: 'Walk' }];
        const diveWithAnims = {
            model: { animations },
            clock: { addTicker: jest.fn() },
        };
        window.DIVEQuickViewPlugin.QuickView.mockResolvedValue(diveWithAnims);

        const plugin = createPlugin();
        await plugin.initViewer();

        const animButton = document.querySelector('.spatial-anim-button');

        mockAnimator.state = 'playing';
        animButton.click();

        mockAnimator.state = 'paused';
        animButton.click();

        expect(mockAnimator.resume).toHaveBeenCalled();
        expect(animButton.classList.contains('spatial-anim-play')).toBe(true);
    });

    test('animator.update patches progress on the circle element', async () => {
        buildDOM();

        const originalUpdateFn = jest.fn();
        const localAnimator = {
            loop: null,
            state: 'playing',
            time: 0,
            duration: 10,
            play: jest.fn(),
            pause: jest.fn(),
            resume: jest.fn(),
            update: originalUpdateFn,
        };
        const localAnimSystem = {
            fromClips: jest.fn().mockResolvedValue(localAnimator),
        };
        window.DIVEAnimationPlugin = {
            AnimationSystem: jest.fn().mockReturnValue(localAnimSystem),
        };

        const animations = [{ name: 'Walk' }];
        const diveWithAnims = {
            model: { animations },
            clock: { addTicker: jest.fn() },
        };
        window.DIVEQuickViewPlugin.QuickView.mockResolvedValue(diveWithAnims);

        const plugin = createPlugin();
        await plugin.initViewer();

        localAnimator.time = 5;
        localAnimator.duration = 10;

        localAnimator.update(0.016);

        const circle = document.querySelector('.spatial-anim-button-circle');
        expect(circle.style.getPropertyValue('--progress')).toBe('0.5');
        expect(originalUpdateFn).toHaveBeenCalledWith(0.016);
    });

    test('animator.update sets progress to 0 when the animation has no duration', async () => {
        buildDOM();

        const originalUpdateFn = jest.fn();
        const localAnimator = {
            ...createMockAnimator(),
            time: 5,
            duration: 0,
            update: originalUpdateFn,
        };
        window.DIVEAnimationPlugin.AnimationSystem.mockReturnValue({
            fromClips: jest.fn().mockResolvedValue(localAnimator),
        });
        window.DIVEQuickViewPlugin.QuickView.mockResolvedValue({
            model: { animations: [{ name: 'Walk' }] },
            clock: { addTicker: jest.fn() },
        });

        const plugin = createPlugin();
        await plugin.initViewer();

        const circle = document.querySelector('.spatial-anim-button-circle');
        circle.style.setProperty('--progress', '0.7');

        localAnimator.update(0.016);

        expect(circle.style.getPropertyValue('--progress')).toBe('0');
    });

    test('initViewer does not show switch when model has only one animation', async () => {
        buildDOM();

        const animations = [{ name: 'Walk' }];
        const diveWithAnims = {
            model: { animations },
            clock: { addTicker: jest.fn() },
        };
        window.DIVEQuickViewPlugin.QuickView.mockResolvedValue(diveWithAnims);

        const plugin = createPlugin();
        await plugin.initViewer();

        const switchContainer = document.querySelector('.spatial-anim-switch-container');
        expect(switchContainer.classList.contains('visually-hidden')).toBe(true);
    });

    test('initViewer shows switch and populates options when model has multiple animations', async () => {
        buildDOM();

        const animations = [{ name: 'Walk' }, { name: 'Run' }, { name: 'Idle' }];
        const diveWithAnims = {
            model: { animations },
            clock: { addTicker: jest.fn() },
        };
        window.DIVEQuickViewPlugin.QuickView.mockResolvedValue(diveWithAnims);

        const plugin = createPlugin();
        await plugin.initViewer();

        const switchContainer = document.querySelector('.spatial-anim-switch-container');
        expect(switchContainer.classList.contains('visually-hidden')).toBe(false);

        const selectEl = document.querySelector('.spatial-anim-switch');
        expect(selectEl.options.length).toBe(3);
        expect(selectEl.options[0].value).toBe('Walk');
        expect(selectEl.options[1].value).toBe('Run');
        expect(selectEl.options[2].value).toBe('Idle');
    });

    test('changing animation select plays selected animation', async () => {
        buildDOM();

        const animations = [{ name: 'Walk' }, { name: 'Run' }];
        const diveWithAnims = {
            model: { animations },
            clock: { addTicker: jest.fn() },
        };
        window.DIVEQuickViewPlugin.QuickView.mockResolvedValue(diveWithAnims);

        const plugin = createPlugin();
        await plugin.initViewer();

        const selectEl = document.querySelector('.spatial-anim-switch');
        selectEl.value = 'Run';
        selectEl.dispatchEvent(new Event('change'));

        expect(mockAnimator.play).toHaveBeenCalledWith('Run');

        const animButton = document.querySelector('.spatial-anim-button');
        expect(animButton.classList.contains('spatial-anim-play')).toBe(true);

        const circle = document.querySelector('.spatial-anim-button-circle');
        expect(circle.style.getPropertyValue('--progress')).toBe('0');
    });

    test('initViewer returns early when animation container is missing', async () => {
        buildDOM({ withAnimContainer: false });

        const animations = [{ name: 'Walk' }];
        const diveWithAnims = {
            model: { animations },
            clock: { addTicker: jest.fn() },
        };
        window.DIVEQuickViewPlugin.QuickView.mockResolvedValue(diveWithAnims);

        const plugin = createPlugin();
        await plugin.initViewer();

        expect(window.DIVEAnimationPlugin.AnimationSystem).toHaveBeenCalled();
        const animButton = document.querySelector('.spatial-anim-button');
        expect(animButton).toBeNull();
    });

    test('initViewer returns early when animation button is missing', async () => {
        buildDOM({ withButton: false });

        const animations = [{ name: 'Walk' }];
        const diveWithAnims = {
            model: { animations },
            clock: { addTicker: jest.fn() },
        };
        window.DIVEQuickViewPlugin.QuickView.mockResolvedValue(diveWithAnims);

        const plugin = createPlugin();
        await plugin.initViewer();

        expect(window.DIVEAnimationPlugin.AnimationSystem).toHaveBeenCalled();
    });

    test('initViewer shows the animation button without switch when the switch container is missing', async () => {
        buildDOM({ withSwitch: false });

        window.DIVEQuickViewPlugin.QuickView.mockResolvedValue({
            model: { animations: [{ name: 'Walk' }, { name: 'Run' }] },
            clock: { addTicker: jest.fn() },
        });

        const plugin = createPlugin();
        await plugin.initViewer();

        const animButton = document.querySelector('.spatial-anim-button');
        expect(animButton.classList.contains('visually-hidden')).toBe(false);
        expect(document.querySelector('.spatial-anim-switch')).toBeNull();
    });

    test('initViewer keeps the switch container hidden when the select is missing', async () => {
        buildDOM({ withSelect: false });

        window.DIVEQuickViewPlugin.QuickView.mockResolvedValue({
            model: { animations: [{ name: 'Walk' }, { name: 'Run' }] },
            clock: { addTicker: jest.fn() },
        });

        const plugin = createPlugin();
        await plugin.initViewer();

        const switchContainer = document.querySelector('.spatial-anim-switch-container');
        expect(switchContainer.classList.contains('visually-hidden')).toBe(true);
    });

    test('initViewer returns early when circle element is missing', async () => {
        buildDOM({ withCircle: false });

        const animations = [{ name: 'Walk' }];
        const diveWithAnims = {
            model: { animations },
            clock: { addTicker: jest.fn() },
        };
        window.DIVEQuickViewPlugin.QuickView.mockResolvedValue(diveWithAnims);

        const plugin = createPlugin();
        await plugin.initViewer();

        expect(window.DIVEAnimationPlugin.AnimationSystem).toHaveBeenCalled();
    });
});

describe('SpatialBaseViewerPlugin viewer creation', () => {
    let canvas;

    function createPlugin(options) {
        const initSpy = jest.spyOn(SpatialBaseViewerPlugin.prototype, 'init').mockResolvedValue(undefined);
        const plugin = new SpatialBaseViewerPlugin(canvas, options);
        initSpy.mockRestore();

        return plugin;
    }

    beforeEach(() => {
        document.body.innerHTML = `
            <div id="parentDiv">
                <canvas id="canvasEl"></canvas>
            </div>
        `;
        canvas = document.getElementById('canvasEl');

        window.DIVEQuickViewPlugin = {
            QuickView: jest.fn().mockResolvedValue({
                model: null,
                clock: { addTicker: jest.fn() },
                startAsync: jest.fn(),
                stop: jest.fn(),
            }),
        };
    });

    test('builds the viewer from the model url', async () => {
        const plugin = createPlugin({ modelUrl: 'http://test/file.glb', sliderPosition: 0 });

        await plugin.initViewer();

        expect(window.DIVEQuickViewPlugin.QuickView).toHaveBeenCalledWith(
            'http://test/file.glb',
            expect.objectContaining({ canvas }),
        );
    });

    test('uses whatever viewer an overriding plugin builds', async () => {
        const ownViewer = { model: null, clock: { addTicker: jest.fn() }, startAsync: jest.fn(), stop: jest.fn() };
        const plugin = createPlugin({ modelUrl: 'http://test/stand-in.png', sliderPosition: 0 });
        plugin.createQuickView = jest.fn().mockResolvedValue(ownViewer);

        await plugin.initViewer();

        expect(plugin.createQuickView).toHaveBeenCalledWith('http://test/stand-in.png');
        expect(window.DIVEQuickViewPlugin.QuickView).not.toHaveBeenCalled();
        expect(plugin.dive).toBe(ownViewer);
    });

    test('skips the animation setup for a viewer without a model', async () => {
        const viewerWithoutModel = { clock: { addTicker: jest.fn() }, startAsync: jest.fn(), stop: jest.fn() };
        window.DIVEQuickViewPlugin.QuickView.mockResolvedValue(viewerWithoutModel);
        const plugin = createPlugin({ modelUrl: 'http://test/file.glb', sliderPosition: 0 });
        const emitterPublishSpy = jest.spyOn(plugin.$emitter, 'publish');

        await plugin.initViewer();

        expect(plugin.dive).toBe(viewerWithoutModel);
        expect(viewerWithoutModel.clock.addTicker).not.toHaveBeenCalled();
        expect(emitterPublishSpy).toHaveBeenCalledWith('Viewer/initViewer');
    });

    test('reuses the existing viewer when initialized again', async () => {
        const plugin = createPlugin({ modelUrl: 'http://test/file.glb', sliderPosition: 0 });
        const emitterPublishSpy = jest.spyOn(plugin.$emitter, 'publish');

        await plugin.initViewer();
        const firstViewer = plugin.dive;
        await plugin.initViewer();

        expect(window.DIVEQuickViewPlugin.QuickView).toHaveBeenCalledTimes(1);
        expect(plugin.dive).toBe(firstViewer);
        expect(emitterPublishSpy.mock.calls.filter(([event]) => event === 'Viewer/initViewer')).toHaveLength(2);
    });

    test('leaves the canvas empty when no viewer could be built', async () => {
        const plugin = createPlugin({ modelUrl: '', sliderPosition: 0 });
        plugin.createQuickView = jest.fn().mockResolvedValue(null);

        await plugin.initViewer();

        expect(plugin.dive).toBeUndefined();
    });
});

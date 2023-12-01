import SpatialMovementNoteUtil from 'src/plugin/spatial/utils/spatial-movement-note-util';
import DeviceDetection from 'src/helper/device-detection.helper';

/**
 * @package innovation
 */
describe('spatial-movement-note-util', () => {
    let pluginMock = undefined;
    let spatialMovementNoteUtil = undefined;
    let noteEl = undefined;
    let canvasEl = undefined;

    beforeEach(() => {
        const wrapperEl = document.createElement('div');
        canvasEl = document.createElement('canvas');
        noteEl = document.createElement('div');
        noteEl.innerText = 'CLICK';
        noteEl.setAttribute('data-spatial-movement-note', 'true')
        noteEl.setAttribute('data-spatial-movement-note-touch-text', 'TOUCH');
        wrapperEl.appendChild(canvasEl);
        wrapperEl.appendChild(noteEl);

        jest.spyOn(DeviceDetection, 'isTouchDevice').mockImplementation(() => false);

        pluginMock = {
            canvas: canvasEl,
        }
        spatialMovementNoteUtil = new SpatialMovementNoteUtil(pluginMock);
    });

    test('util should exist', () => {
        expect(typeof spatialMovementNoteUtil).toBe('object');
    });

    test('util is initialized with correct values', () => {
        expect(spatialMovementNoteUtil.plugin).toBe(pluginMock);
        expect(spatialMovementNoteUtil.note).toBe(noteEl);
    });

    test('util should update note text if touch device', () => {
        jest.spyOn(DeviceDetection, 'isTouchDevice').mockImplementation(() => true);
        spatialMovementNoteUtil = new SpatialMovementNoteUtil(pluginMock);

        expect(spatialMovementNoteUtil.note.innerText).toBe('TOUCH');
    });

    test('util should not update note text if non-touch device', () => {
        expect(spatialMovementNoteUtil.note.innerText).toBe('CLICK');
    });

    test('note should be hidden once clicked', () => {
        expect(noteEl.classList.contains('spatial-canvas-note--hidden')).toBe(false);
        canvasEl.dispatchEvent(new Event('pointerup'));
        expect(noteEl.classList.contains('spatial-canvas-note--hidden')).toBe(true);
    });
});

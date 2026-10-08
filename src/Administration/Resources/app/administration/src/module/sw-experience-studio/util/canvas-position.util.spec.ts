import { loadExperienceStudioCanvasPosition, saveExperienceStudioCanvasPosition } from './canvas-position.util';

describe('module/sw-experience-studio/util/canvas-position.util', () => {
    beforeEach(() => {
        localStorage.clear();
    });

    it('stores canvas position separately for each user and layout', () => {
        saveExperienceStudioCanvasPosition('user-1', 'layout-1', { x: 120, y: -40 });
        saveExperienceStudioCanvasPosition('user-2', 'layout-1', { x: -80, y: 55 });
        saveExperienceStudioCanvasPosition('user-1', 'layout-2', { x: 32, y: 90 });

        expect(loadExperienceStudioCanvasPosition('user-1', 'layout-1')).toEqual({ x: 120, y: -40 });
        expect(loadExperienceStudioCanvasPosition('user-2', 'layout-1')).toEqual({ x: -80, y: 55 });
        expect(loadExperienceStudioCanvasPosition('user-1', 'layout-2')).toEqual({ x: 32, y: 90 });
    });

    it('ignores malformed or incomplete stored positions', () => {
        localStorage.setItem('sw-experience-studio-canvas-position.user-1.layout-1', '{bad json');
        localStorage.setItem('sw-experience-studio-canvas-position.user-1.layout-2', JSON.stringify({ x: 12 }));

        expect(loadExperienceStudioCanvasPosition('user-1', 'layout-1')).toBeNull();
        expect(loadExperienceStudioCanvasPosition('user-1', 'layout-2')).toBeNull();
    });

    it('does not persist a position without an authenticated user', () => {
        saveExperienceStudioCanvasPosition(null, 'layout-1', { x: 20, y: 30 });

        expect(loadExperienceStudioCanvasPosition(null, 'layout-1')).toBeNull();
        expect(localStorage).toHaveLength(0);
    });
});

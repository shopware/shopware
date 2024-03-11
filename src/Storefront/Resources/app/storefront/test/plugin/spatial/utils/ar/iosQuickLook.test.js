import iosQuickLook from 'src/plugin/spatial/utils/ar/iosQuickLook';

/**
 * @package innovation
 */
describe('iosQuickLook', () => {
    let anchor = undefined;
    let createElementMock = undefined;

    beforeEach(() => {
        window.threeJsAddons = {};
        window.threeJsAddons.USDZExporter = function () {
            return {
                parse: (scene, options = {}) => {
                    return ''
                }
            }
        }
        URL.createObjectURL = jest.fn(() => { return 'TestUrl';});
        anchor = document.createElement('a');
        createElementMock = jest.spyOn(document, 'createElement').mockReturnValue(anchor);
    });

    test('IosQuickLook Anchor is correctly clicked and removed', async () => {
        const ob =  {};
        const setAtr = jest.spyOn(anchor, 'setAttribute');
        const clickAction = jest.spyOn(anchor, 'click');
        const removeAction = jest.spyOn(anchor, 'remove');

        const a = await iosQuickLook(ob);

        expect(anchor.innerHTML).toBe('<picture></picture>');
        expect(setAtr).toHaveBeenLastCalledWith('href', 'TestUrl');
        expect(anchor.style.display).toBe('none');
        expect(clickAction).toHaveBeenCalled();
        expect(removeAction).toHaveBeenCalled();
    });
});

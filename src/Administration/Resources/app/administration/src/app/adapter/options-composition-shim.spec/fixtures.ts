/** @sw-package framework */
import { convertOptionsApiOverrideToCompositionApi } from '../options-composition-shim';

/** @private */
export function convertWithSilencedWarning(
    componentName: string,
    config: Parameters<typeof convertOptionsApiOverrideToCompositionApi>[1],
) {
    const spy = jest.spyOn(console, 'warn').mockImplementation(() => {});
    try {
        return convertOptionsApiOverrideToCompositionApi(componentName, config);
    } finally {
        spy.mockRestore();
    }
}

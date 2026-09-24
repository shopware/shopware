/**
 * @sw-package framework
 */
/* eslint-disable sw-deprecation-rules/private-feature-declarations */
/* eslint-disable jest/require-top-level-describe -- the hooks are registered inside the describe of each spec */
import { mount } from '@vue/test-utils';
import { resetBlockIndex } from 'src/core/factory/twig-block-index';
import '../../../../../store/block-override.store';
import createDataScopeFixture from '../../sw-block-override.spec/test-utils/create-data-scope-fixture';
import { resetShimSlotState } from '../twig-shim-layer';

/**
 * End-to-end specs of the Twig -> native block adapter: register a Twig override with
 * `Shopware.Component.override()` before mounting, mount a host with `<sw-block name>`, assert on the DOM
 * and on deprecation warnings only. Use a unique block name per test.
 *
 * Returns the `console.warn` spy, which every test mutes.
 */
export function setupShimSpec(): { consoleSpy: () => jest.SpyInstance } {
    let consoleSpy: jest.SpyInstance;

    beforeEach(() => {
        consoleSpy = jest.spyOn(console, 'warn').mockImplementation(() => {});
    });

    afterEach(() => {
        consoleSpy.mockRestore();
        resetBlockIndex();
        resetShimSlotState();
    });

    return { consoleSpy: () => consoleSpy };
}

/**
 * Mounts a host component containing a single `<sw-block name="...">` wrapped in
 * `.component-root`. The host component is conditionally rendered via
 * `v-if="renderHost"` so lifecycle tests can toggle it without re-creating the
 * wrapper instance.
 *
 * Additional native `<sw-block extends="...">` elements (for interop tests) can
 * be injected via `nativeExtensions` and are rendered as siblings outside
 * `.component-root`, matching how plugin extension components are structured in
 * production.
 */
export async function createWrapper({
    blockName = 'shim-test-block',
    defaultContent = '<div class="default-content"></div>',
    nativeExtensions = '',
    extraData = {},
    extraOptions = {},
    renderHost = true,
} = {}) {
    const swBlock = await wrapTestComponent('sw-block', { sync: true });
    const swBlockParent = await wrapTestComponent('sw-block-parent', { sync: true });

    return mount(
        {
            template: `
                <div>
                    <div v-if="renderHost" class="component-root">
                        <sw-block name="${blockName}" :data="$dataScope">
                            ${defaultContent}
                        </sw-block>
                    </div>
                    ${nativeExtensions}
                </div>
            `,
            data() {
                return {
                    renderHost,
                    ...extraData,
                };
            },
            ...extraOptions,
        },
        {
            global: {
                plugins: [createDataScopeFixture()],
                components: {
                    'sw-block': swBlock,
                    'sw-block-parent': swBlockParent,
                },
            },
        },
    );
}

type MultiBlockWrapperConfig = {
    rootClass: string;
    blockName: string;
    defaultContent: string;
};

export async function createMultiBlockWrapper(blocks: MultiBlockWrapperConfig[]) {
    const swBlock = await wrapTestComponent('sw-block', { sync: true });
    const swBlockParent = await wrapTestComponent('sw-block-parent', { sync: true });

    return mount(
        {
            template: `
                <div>
                    ${blocks
                        .map(
                            ({ rootClass, blockName, defaultContent }) => `
                                <div class="${rootClass}">
                                    <sw-block name="${blockName}" :data="$dataScope">
                                        ${defaultContent}
                                    </sw-block>
                                </div>
                            `,
                        )
                        .join('')}
                </div>
            `,
        },
        {
            global: {
                plugins: [createDataScopeFixture()],
                components: {
                    'sw-block': swBlock,
                    'sw-block-parent': swBlockParent,
                },
            },
        },
    );
}

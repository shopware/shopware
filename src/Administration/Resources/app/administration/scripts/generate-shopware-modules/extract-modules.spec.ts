/**
 * @sw-package framework
 *
 * Which entries the `shopware:*` registry leaves out because their declaration is `@private`. Each case
 * builds a small Administration tree, so the decision is pinned to the source shape that triggers it.
 */

import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { extractModuleRegistry } from './extract-modules';
import { renderDeclarations } from './render-declarations';

const PRIVATE_DOC = '/**\n * @private\n */';

/**
 * The five branch sources the generator reads, each publishing one public and at least one private entry.
 * `overrides` replaces single files to vary one case.
 */
function administrationTree(overrides: Record<string, string> = {}): Record<string, string> {
    return {
        'src/core/service/util.service.ts': `
import createId from './utils/create-id.utils';
import EventBus from './utils/event-bus.utils';
import { mapProps } from './utils/meteor.utils';
import throttle from 'lodash-es/throttle';

export const debug = { warn: () => {} };

${PRIVATE_DOC}
export const extension = { getName: () => '' };

// a line comment is not a marker, even when it mentions @private
export const format = { date: () => '' };

export default { createId, EventBus, mapProps, throttle, debug, extension, format };
`,
        'src/core/service/utils/create-id.utils.ts': 'export default function createId() { return ""; }\n',
        'src/core/service/utils/event-bus.utils.ts': `const emitter = {};\n\n${PRIVATE_DOC}\nexport default emitter;\n`,
        'src/core/service/utils/meteor.utils.ts': `/**\n * @private\n * @sw-package framework\n */\n\nexport function mapProps() {}\n`,

        'src/core/data/index.js': `
import Criteria from './criteria.data';
import FilterFactory from './filter-factory.data';

export default { Criteria, FilterFactory };
`,
        'src/core/data/criteria.data.ts': 'export default class Criteria {}\n',
        'src/core/data/filter-factory.data.js': `${PRIVATE_DOC}\nexport default class FilterFactory {}\n`,

        'src/app/composables/index.ts': `
import useListing from './use-listing';
import useNotification from './use-notification';

export default { useListing, useNotification };
`,
        'src/app/composables/use-listing.ts': 'export default function useListing() {}\n',
        'src/app/composables/use-notification.ts': `${PRIVATE_DOC}\nexport default function useNotification() {}\n`,

        'src/global.types.ts': `
import type FormFieldMixin from './app/mixin/form-field.mixin';
import type NotificationMixin from './app/mixin/notification.mixin';
import type { TabsStore } from './app/store/tabs.store';
import type { ContextStore } from './app/store/context.store';
import type { OrderStore } from './app/store/order.store';

declare global {
    interface MixinContainer {
        'sw-form-field': typeof FormFieldMixin;
        notification: typeof NotificationMixin;
    }

    interface PiniaRootState {
        tabs: TabsStore;
        context: ContextStore;
        swOrder: OrderStore;
    }
}
`,
        'src/app/mixin/form-field.mixin.ts': 'export default Mixin.register("sw-form-field", {});\n',
        'src/app/mixin/notification.mixin.ts': `${PRIVATE_DOC}\nexport default Mixin.register("notification", {});\n`,
        'src/app/store/tabs.store.ts': 'const tabsStore = {};\n\nexport type TabsStore = typeof tabsStore;\n',
        'src/app/store/context.store.ts': `const ContextStore = {};\n\n${PRIVATE_DOC}\nexport type ContextStore = typeof ContextStore;\n`,
        'src/app/store/order.store.ts': `type OrderStore = {};\n\n${PRIVATE_DOC}\nexport type { OrderStore };\n`,
        ...overrides,
    };
}

const roots: string[] = [];

function registryFor(files: Record<string, string>) {
    const root = fs.mkdtempSync(path.join(os.tmpdir(), 'shopware-modules-'));
    roots.push(root);

    Object.entries(files).forEach(
        ([
            relativePath,
            content,
        ]) => {
            const filePath = path.join(root, relativePath);
            fs.mkdirSync(path.dirname(filePath), { recursive: true });
            fs.writeFileSync(filePath, content);
        },
    );

    return extractModuleRegistry(root);
}

describe('scripts/generate-shopware-modules/extract-modules', () => {
    afterAll(() => {
        roots.forEach((root) => fs.rmSync(root, { recursive: true, force: true }));
    });

    describe('leaves out entries marked @private', () => {
        const registry = registryFor(administrationTree());

        it('reads the doc block of a const declared in the branch source', () => {
            expect(registry['shopware:utils'].exports).toContain('debug');
            expect(registry['shopware:utils'].exports).not.toContain('extension');
        });

        it('follows a default import to the doc block of the declaration it exports', () => {
            expect(registry['shopware:utils'].exports).toContain('createId');
            expect(registry['shopware:utils'].exports).not.toContain('EventBus');
        });

        it('treats a private file header as marking every export of the file', () => {
            expect(registry['shopware:utils'].exports).not.toContain('mapProps');
        });

        it('never treats a package import as private', () => {
            expect(registry['shopware:utils'].exports).toContain('throttle');
        });

        it('ignores @private in a line comment', () => {
            expect(registry['shopware:utils'].exports).toContain('format');
        });

        it('applies to classes and composables behind a default-export literal', () => {
            expect(registry['shopware:data'].exports).toEqual(['Criteria']);
            expect(registry['shopware:composables'].exports).toEqual(['useListing']);
        });

        it('follows a mixin type query to the mixin source', () => {
            expect(Object.keys(registry['shopware:mixins'].subpaths)).toEqual(['sw-form-field']);
        });

        it('follows a store type to its alias, also when a const shares its name or an export list exports it', () => {
            expect(Object.keys(registry['shopware:stores'].subpaths)).toEqual(['tabs']);
        });
    });

    it('keeps an entry whose source has no marker', () => {
        const registry = registryFor(
            administrationTree({
                'src/core/data/filter-factory.data.js': 'export default class FilterFactory {}\n',
            }),
        );

        expect(registry['shopware:data'].exports).toEqual([
            'Criteria',
            'FilterFactory',
        ]);
    });

    it('fails on a relative import it cannot resolve rather than publishing it', () => {
        const files = administrationTree();
        delete files['src/core/data/criteria.data.ts'];

        expect(() => registryFor(files)).toThrow('Cannot resolve "./criteria.data" imported by "src/core/data/index.js".');
    });

    it('declares no shopware:composables root once every composable is left out', () => {
        const registry = registryFor(
            administrationTree({
                'src/app/composables/use-listing.ts': `${PRIVATE_DOC}\nexport default function useListing() {}\n`,
            }),
        );

        expect(registry['shopware:composables'].exports).toEqual([]);
        expect(renderDeclarations(registry)).not.toContain("declare module 'shopware:composables' {");
    });
});

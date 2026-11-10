/**
 * @sw-package framework
 *
 * The Twig block migrations of 6.8, read from `eslint-rules/deprecation-rules/data/v6.8/template-blocks.json`.
 */

import templateBlockData from '../../../../../eslint-rules/deprecation-rules/data/v6.8/template-blocks.json';
import { migrationsFromData, removeTemplateBlock, renameTemplateBlock, templateBlockMigration } from '../helpers';
import type { MigrationData, RemoveTemplateBlockStep, RenameTemplateBlockStep } from '../helpers';

type TemplateBlockStep = RemoveTemplateBlockStep | RenameTemplateBlockStep;

export default migrationsFromData(templateBlockData as MigrationData<TemplateBlockStep>[], {
    'remove-template-block': removeTemplateBlock,
    'rename-template-block': renameTemplateBlock,
}).map((migration) => templateBlockMigration(migration));

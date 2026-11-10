/**
 * @sw-package framework
 *
 * The API usage migrations of 6.8, read from `eslint-rules/deprecation-rules/data/v6.8/api-usage.json`.
 */

import apiUsageData from '../../../../../eslint-rules/deprecation-rules/data/v6.8/api-usage.json';
import {
    apiUsageMigration,
    componentMember,
    importSource,
    injectKey,
    memberUsage,
    migrationsFromData,
    moduleSnippets,
    objectKey,
    removedComponent,
    replaceComponentMember,
    replaceComponentMethodCall,
    stringLiteral,
    templateAttribute,
} from '../helpers';
import type { ApiUsageStep, MigrationData } from '../helpers';

export default migrationsFromData(apiUsageData as MigrationData<ApiUsageStep>[], {
    'member-usage': memberUsage,
    'component-member': componentMember,
    'replace-component-member': replaceComponentMember,
    'replace-component-method-call': replaceComponentMethodCall,
    'inject-key': injectKey,
    'removed-component': removedComponent,
    'import-source': importSource,
    'string-literal': stringLiteral,
    'object-key': objectKey,
    'template-attribute': templateAttribute,
    'module-snippets': moduleSnippets,
}).map((migration) => apiUsageMigration(migration));

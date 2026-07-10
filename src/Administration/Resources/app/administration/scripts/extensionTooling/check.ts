/**
 * @sw-package framework
 */

import fs from 'fs';
import path from 'path';
import { spawnSync } from 'child_process';
import { setupExtensionTooling, type ExtensionToolingManifest, type ExtensionToolingProject } from './setup';

export interface ToolingDiagnostic {
    tool: 'typescript' | 'eslint' | 'tooling';
    owner?: string;
    file: string;
    line: number;
    column: number;
    code: string;
    message: string;
}

interface ExtensionToolingBaseline {
    version: 1;
    typescript: ToolingDiagnostic[];
    eslint: ToolingDiagnostic[];
}

export interface BaselineComparison {
    newDiagnostics: ToolingDiagnostic[];
    baselinedDiagnostics: ToolingDiagnostic[];
    staleDiagnostics: ToolingDiagnostic[];
}

function toPosix(filePath: string): string {
    return filePath.split(path.sep).join('/');
}

function canonicalizePath(filePath: string): string {
    let existingPath = filePath;

    while (!fs.existsSync(existingPath)) {
        const parentPath = path.dirname(existingPath);

        if (parentPath === existingPath) {
            return filePath;
        }

        existingPath = parentPath;
    }

    return path.join(fs.realpathSync(existingPath), path.relative(existingPath, filePath));
}

function normalizeFile(projectRoot: string, filePath: string): string {
    const absolutePath = path.isAbsolute(filePath) ? filePath : path.resolve(projectRoot, filePath);
    const canonicalProjectRoot = canonicalizePath(projectRoot);
    const canonicalFilePath = canonicalizePath(absolutePath);

    return toPosix(path.relative(canonicalProjectRoot, canonicalFilePath));
}

function normalizeMessage(message: string): string {
    return message.replace(/\s+/g, ' ').trim();
}

function diagnosticKey(diagnostic: ToolingDiagnostic): string {
    return [
        diagnostic.tool,
        diagnostic.file,
        diagnostic.line,
        diagnostic.column,
        diagnostic.code,
        normalizeMessage(diagnostic.message),
    ].join('|');
}

export function parseTypeScriptDiagnostics(output: string, projectRoot: string): ToolingDiagnostic[] {
    const diagnostics: ToolingDiagnostic[] = [];
    const diagnosticPatterns = [
        /^(.*)\((\d+),(\d+)\):\s+(?:error|warning)\s+(TS\d+):\s+(.*)$/,
        /^(.*):(\d+):(\d+)\s+-\s+(?:error|warning)\s+(TS\d+):\s+(.*)$/,
    ];

    for (const line of output.split(/\r?\n/)) {
        for (const pattern of diagnosticPatterns) {
            const match = line.match(pattern);

            if (!match) {
                continue;
            }

            diagnostics.push({
                tool: 'typescript',
                file: normalizeFile(projectRoot, match[1]),
                line: Number(match[2]),
                column: Number(match[3]),
                code: match[4],
                message: normalizeMessage(match[5]),
            });
            break;
        }
    }

    return diagnostics;
}

export function parseEslintDiagnostics(output: string, projectRoot: string): ToolingDiagnostic[] {
    if (!output.trim()) {
        return [];
    }

    const parsedResults: unknown = JSON.parse(output);
    const results = parsedResults as Array<{
        filePath: string;
        messages: Array<{
            line?: number;
            column?: number;
            ruleId?: string | null;
            message: string;
            fatal?: boolean;
        }>;
    }>;

    return results.flatMap((result) =>
        result.messages.map((message) => ({
            tool: 'eslint' as const,
            file: normalizeFile(projectRoot, result.filePath),
            line: message.line ?? 0,
            column: message.column ?? 0,
            code: message.ruleId ?? (message.fatal ? 'fatal' : 'unknown'),
            message: normalizeMessage(message.message),
        })),
    );
}

function findOwningProject(
    projectRoot: string,
    projects: ExtensionToolingProject[],
    diagnostic: ToolingDiagnostic,
): ExtensionToolingProject | null {
    if (diagnostic.owner) {
        const ownedProject = projects.find((project) => project.technicalName === diagnostic.owner);

        if (ownedProject) {
            return ownedProject;
        }
    }

    const diagnosticPath = path.resolve(projectRoot, diagnostic.file);

    return (
        projects
            .filter((project) => {
                const sourcePath = path.resolve(projectRoot, project.sourcePath);
                const relativePath = path.relative(sourcePath, diagnosticPath);

                return relativePath === '' || (!relativePath.startsWith('..' + path.sep) && relativePath !== '..');
            })
            .sort((left, right) => right.sourcePath.length - left.sourcePath.length)[0] ?? null
    );
}

function toPortableDiagnostic(
    projectRoot: string,
    project: ExtensionToolingProject,
    diagnostic: ToolingDiagnostic,
): ToolingDiagnostic {
    const basePath = path.resolve(projectRoot, project.basePath);
    const diagnosticPath = path.resolve(projectRoot, diagnostic.file);
    const portableDiagnostic = { ...diagnostic };

    delete portableDiagnostic.owner;

    return {
        ...portableDiagnostic,
        file: toPosix(path.relative(basePath, diagnosticPath)),
    };
}

function readBaseline(projectRoot: string, project: ExtensionToolingProject): ExtensionToolingBaseline {
    const baselinePath = path.resolve(projectRoot, project.baseline);

    if (!fs.existsSync(baselinePath)) {
        return {
            version: 1,
            typescript: [],
            eslint: [],
        };
    }

    return JSON.parse(fs.readFileSync(baselinePath, 'utf8')) as ExtensionToolingBaseline;
}

export function compareWithBaselines(
    projectRoot: string,
    projects: ExtensionToolingProject[],
    diagnostics: ToolingDiagnostic[],
): BaselineComparison {
    const newDiagnostics: ToolingDiagnostic[] = [];
    const baselinedDiagnostics: ToolingDiagnostic[] = [];
    const staleDiagnostics: ToolingDiagnostic[] = [];
    const baselines = new Map<string, ExtensionToolingBaseline>();
    const baselineKeys = new Map<string, Set<string>>();
    const currentKeysByBaseline = new Map<string, Set<string>>();

    for (const project of projects) {
        if (baselines.has(project.baseline)) {
            continue;
        }

        const baseline = readBaseline(projectRoot, project);

        baselines.set(project.baseline, baseline);
        baselineKeys.set(
            project.baseline,
            new Set(
                [
                    ...baseline.typescript,
                    ...baseline.eslint,
                ].map(diagnosticKey),
            ),
        );
    }

    for (const diagnostic of diagnostics) {
        const project = findOwningProject(projectRoot, projects, diagnostic);

        if (!project) {
            newDiagnostics.push(diagnostic);
            continue;
        }

        const portableDiagnostic = toPortableDiagnostic(projectRoot, project, diagnostic);
        const projectBaselineKeys = baselineKeys.get(project.baseline) ?? new Set<string>();
        const currentKeys = currentKeysByBaseline.get(project.baseline) ?? new Set<string>();

        currentKeys.add(diagnosticKey(portableDiagnostic));
        currentKeysByBaseline.set(project.baseline, currentKeys);

        if (projectBaselineKeys.has(diagnosticKey(portableDiagnostic))) {
            baselinedDiagnostics.push(diagnostic);
        } else {
            newDiagnostics.push(diagnostic);
        }
    }

    const handledBaselines = new Set<string>();

    for (const project of projects) {
        if (handledBaselines.has(project.baseline)) {
            continue;
        }

        handledBaselines.add(project.baseline);
        const baseline = baselines.get(project.baseline) ?? {
            version: 1,
            typescript: [],
            eslint: [],
        };
        const currentKeys = currentKeysByBaseline.get(project.baseline) ?? new Set<string>();

        for (const diagnostic of [
            ...baseline.typescript,
            ...baseline.eslint,
        ]) {
            if (!currentKeys.has(diagnosticKey(diagnostic))) {
                staleDiagnostics.push({
                    ...diagnostic,
                    file: toPosix(path.join(project.basePath, diagnostic.file)),
                });
            }
        }
    }

    return {
        newDiagnostics,
        baselinedDiagnostics,
        staleDiagnostics,
    };
}

function writeBaselineForProjects(
    projectRoot: string,
    projects: ExtensionToolingProject[],
    diagnostics: ToolingDiagnostic[],
): void {
    const project = projects[0];

    if (!project) {
        return;
    }

    const projectDiagnostics = diagnostics
        .filter((diagnostic) => findOwningProject(projectRoot, projects, diagnostic) !== null)
        .map((diagnostic) => toPortableDiagnostic(projectRoot, project, diagnostic));
    const baseline: ExtensionToolingBaseline = {
        version: 1,
        typescript: projectDiagnostics.filter((diagnostic) => diagnostic.tool === 'typescript'),
        eslint: projectDiagnostics.filter((diagnostic) => diagnostic.tool === 'eslint'),
    };
    const baselinePath = path.resolve(projectRoot, project.baseline);

    fs.writeFileSync(baselinePath, JSON.stringify(baseline, null, 2) + '\n');
}

export function writeBaseline(
    projectRoot: string,
    project: ExtensionToolingProject,
    diagnostics: ToolingDiagnostic[],
): void {
    writeBaselineForProjects(projectRoot, [project], diagnostics);
}

function createToolingDiagnostic(projectRoot: string, code: string, message: string): ToolingDiagnostic {
    return {
        tool: 'tooling',
        file: normalizeFile(projectRoot, projectRoot),
        line: 0,
        column: 0,
        code,
        message: normalizeMessage(message),
    };
}

function runTypeScript(
    projectRoot: string,
    administrationRoot: string,
    manifest: ExtensionToolingManifest,
): ToolingDiagnostic[] {
    const vueTscPath = path.join(administrationRoot, 'node_modules', 'vue-tsc', 'bin', 'vue-tsc.js');
    const projectConfigs = new Map<string, ExtensionToolingProject[]>();

    for (const project of manifest.projects) {
        const projectConfig = path.resolve(projectRoot, project.checkTsconfig);
        const owners = projectConfigs.get(projectConfig) ?? [];

        owners.push(project);
        projectConfigs.set(projectConfig, owners);
    }

    if (!fs.existsSync(vueTscPath)) {
        return [createToolingDiagnostic(projectRoot, 'vue-tsc', 'vue-tsc is not installed in the Administration.')];
    }

    const diagnostics: ToolingDiagnostic[] = [];

    for (const [
        projectConfig,
        owners,
    ] of projectConfigs) {
        const result = spawnSync(
            process.execPath,
            [
                vueTscPath,
                '--noEmit',
                '--pretty',
                'false',
                '--project',
                projectConfig,
            ],
            {
                cwd: projectRoot,
                encoding: 'utf8',
                maxBuffer: 100 * 1024 * 1024,
            },
        );
        const output = (result.stdout ?? '') + '\n' + (result.stderr ?? '');
        const parsedDiagnostics = parseTypeScriptDiagnostics(output, projectRoot);

        diagnostics.push(
            ...parsedDiagnostics.map((diagnostic) => ({
                ...diagnostic,
                owner: owners[0]?.technicalName,
            })),
        );

        if (result.status !== 0 && parsedDiagnostics.length === 0) {
            diagnostics.push({
                ...createToolingDiagnostic(projectRoot, 'vue-tsc', output || 'vue-tsc failed.'),
                owner: owners[0]?.technicalName,
            });
        }
    }

    return diagnostics;
}

function readEslintMajor(administrationRoot: string): number {
    const packagePath = path.join(administrationRoot, 'node_modules', 'eslint', 'package.json');
    const packageJson = JSON.parse(fs.readFileSync(packagePath, 'utf8')) as { version: string };

    return Number(packageJson.version.split('.')[0]);
}

function runEslint(
    projectRoot: string,
    administrationRoot: string,
    manifest: ExtensionToolingManifest,
): ToolingDiagnostic[] {
    if (manifest.projects.length === 0) {
        return [];
    }

    const eslintPath = path.join(administrationRoot, 'node_modules', 'eslint', 'bin', 'eslint.js');
    const args = [eslintPath];

    if (readEslintMajor(administrationRoot) < 10) {
        args.push('--flag', 'v10_config_lookup_from_file');
    }

    args.push(
        '--format',
        'json',
        '--no-error-on-unmatched-pattern',
        ...manifest.projects.map((project) => project.sourcePath),
    );

    const result = spawnSync(process.execPath, args, {
        cwd: projectRoot,
        encoding: 'utf8',
        maxBuffer: 100 * 1024 * 1024,
    });

    try {
        const diagnostics = parseEslintDiagnostics(result.stdout ?? '', projectRoot);

        if (result.status !== 0 && diagnostics.length === 0) {
            return [createToolingDiagnostic(projectRoot, 'eslint', result.stderr || 'ESLint failed.')];
        }

        return diagnostics;
    } catch (error) {
        return [
            createToolingDiagnostic(
                projectRoot,
                'eslint',
                (result.stderr ?? '') + ' ' + (error instanceof Error ? error.message : String(error)),
            ),
        ];
    }
}

function readArgument(name: string): string | undefined {
    const prefix = '--' + name + '=';
    const argument = process.argv.find((value) => value.startsWith(prefix));

    return argument?.slice(prefix.length);
}

function printDiagnostic(diagnostic: ToolingDiagnostic): void {
    console.error(
        diagnostic.file + ':' + diagnostic.line + ':' + diagnostic.column + ' ' + diagnostic.code + ' ' + diagnostic.message,
    );
}

if (require.main === module) {
    const administrationRoot = path.resolve(readArgument('administration-root') ?? path.resolve(__dirname, '../..'));
    const projectRootValue = readArgument('project-root') ?? process.env.PROJECT_ROOT;

    if (!projectRootValue) {
        throw new Error('PROJECT_ROOT or --project-root is required.');
    }

    const projectRoot = path.resolve(projectRootValue);
    const setupResult = setupExtensionTooling({
        projectRoot,
        administrationRoot,
        pluginsConfigPath: readArgument('plugins-config'),
    });
    const diagnostics = [
        ...runTypeScript(projectRoot, administrationRoot, setupResult.manifest),
        ...runEslint(projectRoot, administrationRoot, setupResult.manifest),
    ];
    const updateBaseline = readArgument('update-baseline');

    if (updateBaseline) {
        const selectedProjects =
            updateBaseline === 'all'
                ? setupResult.manifest.projects
                : setupResult.manifest.projects.filter((project) => project.technicalName === updateBaseline);

        if (selectedProjects.length === 0) {
            throw new Error('Unknown Administration extension project: ' + updateBaseline);
        }

        const baselineGroups = new Map<string, ExtensionToolingProject[]>();

        for (const project of selectedProjects) {
            const projects = baselineGroups.get(project.baseline) ?? [];

            projects.push(project);
            baselineGroups.set(project.baseline, projects);
        }

        for (const projects of baselineGroups.values()) {
            writeBaselineForProjects(projectRoot, projects, diagnostics);
            console.log(
                'Updated Administration tooling baseline for ' +
                    projects.map((project) => project.technicalName).join(', ') +
                    '.',
            );
        }
    }

    const comparison = compareWithBaselines(projectRoot, setupResult.manifest.projects, diagnostics);

    for (const diagnostic of comparison.newDiagnostics) {
        printDiagnostic(diagnostic);
    }

    if (comparison.staleDiagnostics.length > 0) {
        console.warn(
            comparison.staleDiagnostics.length +
                ' stale baseline diagnostic(s) can be removed by updating the affected baseline.',
        );
    }

    console.log(
        'Administration extension checks: ' +
            comparison.newDiagnostics.length +
            ' new, ' +
            comparison.baselinedDiagnostics.length +
            ' baselined, ' +
            comparison.staleDiagnostics.length +
            ' stale.',
    );

    if (comparison.newDiagnostics.length > 0) {
        process.exitCode = 1;
    }
}

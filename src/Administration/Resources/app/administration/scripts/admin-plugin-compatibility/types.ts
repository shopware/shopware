/**
 * @sw-package framework
 */
/* eslint-disable sw-deprecation-rules/private-feature-declarations */

export type CliOptions = {
    profile: string;
    commercialPath: string;
    commercialLicenseGenerator: string;
    commercialLicenseKeyFile: string;
    commercialLicenseHost: string;
    commercialLicensePlan: string;
    commercialConsoleCommand: string;
    forceLicense: boolean;
    components: string[];
    reportDir: string;
    baselineFile: string;
    skipBuild: boolean;
    writeBaseline: boolean;
};

export type FailureClass = 'ci' | 'setup' | 'license' | 'build' | 'runtime';

export type CommandPhase = 'setup' | 'license' | 'build' | 'runtime';

export type CommandRequest = {
    name: string;
    command: string;
    phase: CommandPhase;
    cwd: string;
};

export type CommandResult = CommandRequest & {
    stdout: string;
    stderr: string;
    exitCode: number | null;
    durationMs: number;
    startedAt: string;
};

export type StepResult = {
    name: string;
    phase: CommandPhase;
    status: 'passed' | 'failed' | 'skipped';
    message?: string;
    command?: CommandResult;
};

export type ParsedCliArguments =
    | { type: 'options'; options: CliOptions }
    | { type: 'help'; help: string }
    | { type: 'error'; message: string; help: string };

export type PreflightResult = {
    status: 'passed' | 'failed';
    profile: string;
    reportDir: string;
    ciGuardVariables: string[];
};

export type RunnerResult = {
    status: 'passed' | 'failed';
    exitCode: number;
    failureClass?: FailureClass;
    profile: string;
    reportDir: string;
    commercialPath: string;
    licenseHost: string;
    licensePlan: string;
    coverageGaps: string[];
    ciGuardVariables: string[];
    steps: StepResult[];
    commands: CommandResult[];
    baseline?: {
        status: 'missing' | 'written' | 'matched' | 'changed';
        path: string;
        differences: string[];
    };
    reportPaths?: {
        json: string;
        markdown: string;
    };
};

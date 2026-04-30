/**
 * @sw-package framework
 */
/* eslint-disable sw-deprecation-rules/private-feature-declarations */

import fs from 'fs';
import { EXIT_CODES, resolveProjectRoot } from './constants';
import { buildAdminBuildRequest } from './build';
import { classifyCommandFailure, isCommandNotFound } from './classifier';
import { resolveComponentSmokeSelection } from './components';
import { getCiGuardVariables } from './environment';
import { runCommand as defaultRunCommand } from './command';
import { writeCompatibilityReports } from './reports';
import { buildSmokeTestRequest } from './smoke';
import {
    buildCacheClearRequest,
    buildCommercialNpmInstallRequest,
    buildLicenseGeneratorPreflightRequest,
    buildLicenseGeneratorRequest,
    buildLicenseHostValidationRequest,
    buildPluginInstallRequest,
    buildPluginRefreshRequest,
    isBundledLicenseGenerator,
    licenseHostValidationPassed,
    resolveCommercialLicenseKeyFile,
    resolveCommercialPath,
    getCommercialNpmInstallState,
    validateCommercialCheckout,
} from './commercial';
import type { CliOptions, CommandRequest, CommandResult, FailureClass, RunnerResult, StepResult } from './types';

type RunnerDependencies = {
    env?: NodeJS.ProcessEnv;
    projectRoot?: string;
    fileSystem?: Pick<typeof fs, 'existsSync' | 'readFileSync'>;
    runCommand?: (request: CommandRequest) => Promise<CommandResult>;
};

export async function runCompatibilityWorkflow(options: CliOptions, dependencies: RunnerDependencies = {}): Promise<RunnerResult> {
    const env = dependencies.env ?? process.env;
    const projectRoot = dependencies.projectRoot ?? resolveProjectRoot();
    const fileSystem = dependencies.fileSystem ?? fs;
    const executeCommand = dependencies.runCommand ?? defaultRunCommand;
    const commercialPath = resolveCommercialPath(projectRoot, options.commercialPath);
    const steps: StepResult[] = [];
    const commands: CommandResult[] = [];
    const ciGuardVariables = getCiGuardVariables(env);
    const smokeSelection = resolveComponentSmokeSelection(options.components);

    if (ciGuardVariables.length > 0) {
        return createResult(
            options,
            projectRoot,
            commercialPath,
            ciGuardVariables,
            steps,
            commands,
            smokeSelection.coverageGaps,
            'ci',
            EXIT_CODES.ci,
        );
    }

    const checkoutValidation = validateCommercialCheckout(commercialPath, fileSystem);

    if (!checkoutValidation.valid) {
        steps.push({
            name: 'commercial:checkout',
            phase: 'setup',
            status: 'failed',
            message: checkoutValidation.message,
        });

        return createResult(
            options,
            projectRoot,
            commercialPath,
            ciGuardVariables,
            steps,
            commands,
            smokeSelection.coverageGaps,
            'setup',
            EXIT_CODES.setup,
        );
    }

    steps.push({
        name: 'commercial:checkout',
        phase: 'setup',
        status: 'passed',
        message: checkoutValidation.composerJsonPath,
    });

    const generatorPreflight = await executeCommand(buildLicenseGeneratorPreflightRequest(options, projectRoot));

    commands.push(generatorPreflight);

    if (isCommandNotFound(generatorPreflight)) {
        steps.push({
            name: generatorPreflight.name,
            phase: generatorPreflight.phase,
            status: 'failed',
            message: `License generator command was not found: ${options.commercialLicenseGenerator}`,
            command: generatorPreflight,
        });

        return createResult(
            options,
            projectRoot,
            commercialPath,
            ciGuardVariables,
            steps,
            commands,
            smokeSelection.coverageGaps,
            'setup',
            EXIT_CODES.setup,
        );
    }

    const bundledLicenseGenerator = isBundledLicenseGenerator(generatorPreflight);
    const commercialLicenseKeyFile = bundledLicenseGenerator ? resolveCommercialLicenseKeyFile(projectRoot, options, env) : '';

    if (bundledLicenseGenerator && commercialLicenseKeyFile === '') {
        steps.push({
            name: generatorPreflight.name,
            phase: generatorPreflight.phase,
            status: 'failed',
            message: 'Bundled commercial-license-generator fallback requires --commercial-license-key-file or ADMIN_PLUGIN_COMPATIBILITY_COMMERCIAL_LICENSE_KEY_FILE before plugin mutations.',
            command: generatorPreflight,
        });

        return createResult(
            options,
            projectRoot,
            commercialPath,
            ciGuardVariables,
            steps,
            commands,
            smokeSelection.coverageGaps,
            'setup',
            EXIT_CODES.setup,
        );
    }

    if (bundledLicenseGenerator && !fileSystem.existsSync(commercialLicenseKeyFile)) {
        steps.push({
            name: generatorPreflight.name,
            phase: generatorPreflight.phase,
            status: 'failed',
            message: `Commercial dev license key file was not found: ${commercialLicenseKeyFile}`,
            command: generatorPreflight,
        });

        return createResult(
            options,
            projectRoot,
            commercialPath,
            ciGuardVariables,
            steps,
            commands,
            smokeSelection.coverageGaps,
            'setup',
            EXIT_CODES.setup,
        );
    }

    steps.push({
        name: generatorPreflight.name,
        phase: generatorPreflight.phase,
        status: 'passed',
        message: `License generator command is available: ${options.commercialLicenseGenerator}`,
        command: generatorPreflight,
    });

    const setupRequests = [
        buildPluginRefreshRequest(options, projectRoot),
        buildPluginInstallRequest(options, projectRoot),
    ];
    const npmInstallState = getCommercialNpmInstallState(commercialPath, fileSystem);

    if (npmInstallState.shouldInstall) {
        setupRequests.push(buildCommercialNpmInstallRequest(commercialPath));
    } else {
        steps.push({
            name: 'commercial:npm-install',
            phase: 'setup',
            status: 'skipped',
            message: npmInstallState.reason,
        });
    }

    const setupResult = await runRequests(setupRequests, executeCommand, steps, commands);

    if (!setupResult.passed) {
        return createResult(
            options,
            projectRoot,
            commercialPath,
            ciGuardVariables,
            steps,
            commands,
            smokeSelection.coverageGaps,
            setupResult.failureClass,
            getExitCode(setupResult.failureClass),
        );
    }

    const licenseRequests = [
        buildLicenseGeneratorRequest(options, projectRoot, commercialLicenseKeyFile),
        buildCacheClearRequest(options, projectRoot),
        buildLicenseHostValidationRequest(options, projectRoot),
    ];
    const licenseResult = await runRequests(licenseRequests, executeCommand, steps, commands);
    const licenseHostCommand = commands.find((command) => command.name === 'commercial:license-host');

    if (!licenseResult.passed) {
        return createResult(
            options,
            projectRoot,
            commercialPath,
            ciGuardVariables,
            steps,
            commands,
            smokeSelection.coverageGaps,
            licenseResult.failureClass,
            getExitCode(licenseResult.failureClass),
        );
    }

    if (!licenseHostCommand || !licenseHostValidationPassed(licenseHostCommand, options.commercialLicenseHost)) {
        if (licenseHostCommand && !licenseHostValidationPassed(licenseHostCommand, options.commercialLicenseHost)) {
            const step = steps.find((currentStep) => currentStep.name === 'commercial:license-host');

            if (step) {
                step.status = 'failed';
                step.message = `Expected ${options.commercialLicenseHost} in ${licenseHostCommand.name} output.`;
            }
        }

        return createResult(
            options,
            projectRoot,
            commercialPath,
            ciGuardVariables,
            steps,
            commands,
            smokeSelection.coverageGaps,
            'license',
            EXIT_CODES.license,
        );
    }

    if (options.skipBuild) {
        steps.push({
            name: 'admin:build',
            phase: 'build',
            status: 'skipped',
            message: '--skip-build was set.',
        });
    } else {
        const buildResult = await runRequests([buildAdminBuildRequest(projectRoot)], executeCommand, steps, commands);

        if (!buildResult.passed) {
            return createResult(
                options,
                projectRoot,
                commercialPath,
                ciGuardVariables,
                steps,
                commands,
                smokeSelection.coverageGaps,
                buildResult.failureClass,
                getExitCode(buildResult.failureClass),
            );
        }
    }

    const smokeResult = await runRequests(
        [buildSmokeTestRequest(projectRoot, smokeSelection.cases.map((smokeCase) => smokeCase.tag))],
        executeCommand,
        steps,
        commands,
    );

    if (!smokeResult.passed) {
        return createResult(
            options,
            projectRoot,
            commercialPath,
            ciGuardVariables,
            steps,
            commands,
            smokeSelection.coverageGaps,
            smokeResult.failureClass,
            getExitCode(smokeResult.failureClass),
        );
    }

    return createResult(
        options,
        projectRoot,
        commercialPath,
        ciGuardVariables,
        steps,
        commands,
        smokeSelection.coverageGaps,
        undefined,
        EXIT_CODES.success,
    );
}

async function runRequests(
    requests: CommandRequest[],
    executeCommand: (request: CommandRequest) => Promise<CommandResult>,
    steps: StepResult[],
    commands: CommandResult[],
): Promise<{ passed: true } | { passed: false; failureClass: FailureClass }> {
    for (const request of requests) {
        const result = await executeCommand(request);

        commands.push(result);
        steps.push({
            name: request.name,
            phase: request.phase,
            status: result.exitCode === 0 ? 'passed' : 'failed',
            command: result,
        });

        if (result.exitCode !== 0) {
            return {
                passed: false,
                failureClass: classifyCommandFailure(result),
            };
        }
    }

    return { passed: true };
}

function getExitCode(failureClass: FailureClass): number {
    return EXIT_CODES[failureClass];
}

function createResult(
    options: CliOptions,
    projectRoot: string,
    commercialPath: string,
    ciGuardVariables: string[],
    steps: StepResult[],
    commands: CommandResult[],
    coverageGaps: string[],
    failureClass: FailureClass | undefined,
    exitCode: number,
): RunnerResult {
    const result: RunnerResult = {
        status: failureClass ? 'failed' : 'passed',
        exitCode,
        failureClass,
        profile: options.profile,
        reportDir: options.reportDir,
        commercialPath,
        licenseHost: options.commercialLicenseHost,
        licensePlan: options.commercialLicensePlan,
        coverageGaps,
        ciGuardVariables,
        steps,
        commands,
    };

    if (failureClass !== 'ci') {
        const reportResult = writeCompatibilityReports(result, projectRoot, {
            baselineFile: options.baselineFile,
            writeBaseline: options.writeBaseline,
        });

        result.reportPaths = reportResult.paths;
        result.baseline = reportResult.baseline;
    }

    return result;
}

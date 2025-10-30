/**
 * @sw-package framework
 * 
 * Wrapper script for Vite dev server that manages the dev server flag file.
 * This allows the Twig templates to detect when dev server mode is active.
 */
/* eslint-disable no-console */
const { spawn } = require('node:child_process');
const { writeFileSync, unlinkSync, existsSync } = require('node:fs');
const { resolve } = require('node:path');
const chalk = require('chalk');

// Use PROJECT_ROOT from composer or fallback to relative path
const projectRoot = process.env.PROJECT_ROOT || resolve(__dirname, '../../../../../..');
const flagFile = resolve(projectRoot, 'var/vite-dev-server.flag');

// Create flag file
console.log(chalk.blue('Creating dev server flag file...'));
writeFileSync(flagFile, new Date().toISOString());
console.log(chalk.green('✓ Dev server mode enabled\n'));

// Start Vite dev server
const vite = spawn('npx', ['vite', '--mode', 'development'], {
    stdio: 'inherit',
    shell: true,
    env: process.env,
});

// Cleanup function
const cleanup = () => {
    console.log(chalk.blue('\n\nShutting down dev server...'));
    
    // Remove flag file
    if (existsSync(flagFile)) {
        unlinkSync(flagFile);
        console.log(chalk.green('✓ Dev server mode disabled'));
    }
    
    // Kill Vite process
    vite.kill();
    
    process.exit(0);
};

// Handle termination signals
process.on('SIGINT', cleanup);
process.on('SIGTERM', cleanup);
process.on('exit', cleanup);

// Handle Vite exit
vite.on('exit', (code) => {
    if (existsSync(flagFile)) {
        unlinkSync(flagFile);
    }
    process.exit(code);
});


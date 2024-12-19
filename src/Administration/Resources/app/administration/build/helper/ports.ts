import * as net from 'net';
import chalk from "chalk";

function isPortFree(port) {
    return new Promise((resolve, reject) => {
        const server = net.createServer();

        server.listen({ port }, () => {
            server.close(() => {
                console.log(chalk.green(`Port ${port} is free`));
                resolve(true);
            });
        });

        server.on('error', (err) => {
            if (err.code === 'EADDRINUSE') {
                console.log(chalk.yellow(`Port ${port} is in use`));
                resolve(false);
            } else {
                console.error(chalk.red(`Error checking port ${port}:`, err));
                reject(err);
            }
        });
    });
}

export default async function findAvailablePorts(startPort = 5173, requiredPorts = 1) {
    const ports = [];
    let currentPort = startPort;
    const maxPort = 6333;

    console.log(chalk.blue(`Searching for ${requiredPorts} free ports starting from ${startPort}`));

    while (ports.length < requiredPorts) {
        if (currentPort > maxPort) {
            throw new Error(`No free ports found between ${startPort} and ${maxPort}`);
        }

        try {
            const isFree = await isPortFree(currentPort);
            if (isFree) {
                ports.push(currentPort);
                console.log(chalk.green(`Found free port: ${currentPort}`));
            }
            currentPort++;
        } catch (error) {
            console.error(chalk.red('Error finding ports:', error));
            throw error;
        }
    }

    return ports;
}

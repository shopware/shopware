import '@babel/polyfill';
import bootstrap from 'bootstrap5';

// log rejections so that they are not printed to stderr as a fallback
process.on('unhandledRejection', (reason) => {
    console.log('REJECTION', reason);
});

global.bootstrap = bootstrap;

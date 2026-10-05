import fs from 'node:fs';
import {spawn} from 'node:child_process';
if(fs.existsSync('.env'))process.loadEnvFile('.env');
const mode=process.argv[2]||'--once';
if(!['--once','--loop','--check'].includes(mode))throw Error('Unknown mode');
const child=spawn(process.env.PHP_BINARY||'php',['runtime/worker.php',mode],{stdio:'inherit',env:process.env});
child.on('error',()=>{console.error('PHP unavailable. Set PHP_BINARY in .env.');process.exitCode=1});
for(const signal of ['SIGINT','SIGTERM'])process.on(signal,()=>child.kill(signal));
child.on('exit',code=>process.exit(code??1));

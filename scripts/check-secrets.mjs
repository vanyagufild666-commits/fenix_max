import fs from 'node:fs';
import {execFileSync} from 'node:child_process';
if(fs.existsSync('.env'))process.loadEnvFile('.env');
const secrets=['MAX_BOT_TOKEN','MAX_USER_ID'].map(key=>process.env[key]).filter(Boolean);
const files=execFileSync('git',['ls-files','--cached','--others','--exclude-standard','-z'],{encoding:'utf8'}).split('\0').filter(Boolean);
for(const file of files){if(fs.existsSync(file)&&secrets.some(value=>fs.readFileSync(file).includes(Buffer.from(value)))){console.error('Private configuration detected in '+file);process.exit(1)}}
if(files.includes('.env'))throw Error('Actual .env must be ignored');
console.log('Private token and recipient are absent from Git files.');

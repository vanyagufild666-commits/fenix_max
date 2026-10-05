import fs from 'node:fs';
import {spawnSync} from 'node:child_process';
if(fs.existsSync('.env'))process.loadEnvFile('.env');
const result=spawnSync(process.execPath,['--test','tests/max-delivery.test.mjs','tests/manager-proof.test.mjs'],{stdio:'inherit',env:process.env});
process.exit(result.status??1);

import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import {spawnSync} from 'node:child_process';
test('REG relay delivers exactly one claim, preserves failures for retry and requires a MAX receipt',()=>{
 const prefix=path.join(os.tmpdir(),'fenix-max-test-'),directory=fs.mkdtempSync(prefix);
 try{
  for(const name of ['core.php','max-notifications.php'])fs.copyFileSync('runtime/'+name,path.join(directory,name));
  fs.copyFileSync('runtime/migration.sql',path.join(directory,'migration.sql'));
  const run=spawnSync(process.env.PHP_BINARY||'php',['tests/max-delivery.php',directory],{encoding:'utf8'});
  assert.equal(run.status,0,run.stdout+run.stderr);assert.match(run.stdout,/MAX delivery tests passed/);
 }finally{assert.ok(path.resolve(directory).startsWith(path.resolve(prefix)));fs.rmSync(directory,{recursive:true,force:true});}
});

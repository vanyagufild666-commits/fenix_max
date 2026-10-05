// Operator-only pairing. Never give the manager the token or server credentials.
import fs from 'node:fs';
import https from 'node:https';
import crypto from 'node:crypto';
import {managerMessage} from './manager-proof.mjs';
if(fs.existsSync('.env'))process.loadEnvFile('.env');
const mode=process.argv[2],file='outputs/manager-connection.json';
const agent=new https.Agent({ca:fs.readFileSync(new URL('../runtime/certs/russian-trusted-root-ca.pem',import.meta.url))});
function api(path){return new Promise((resolve,reject)=>{
 const request=https.get('https://platform-api2.max.ru'+path,{agent,headers:{Authorization:process.env.MAX_BOT_TOKEN},timeout:20000},response=>{
  const parts=[];response.on('data',part=>parts.push(part));response.on('error',reject);response.on('end',()=>{
   if(response.statusCode!==200)return reject(Error('MAX_HTTP_'+response.statusCode));
   try{resolve(JSON.parse(Buffer.concat(parts).toString('utf8')))}catch{reject(Error('MAX_RESPONSE_INVALID'))}
  });
 });request.on('error',()=>reject(Error('MAX_CONNECTION_FAILED')));request.on('timeout',()=>request.destroy(Error('MAX_TIMEOUT')));
});}
function stateSave(state){fs.mkdirSync('outputs',{recursive:true});fs.writeFileSync(file,JSON.stringify(state),{mode:0o600});}
function setEnv(key,value){const text=fs.readFileSync('.env','utf8'),pattern=new RegExp('^'+key+'=.*$','m'),line=key+'='+value;fs.writeFileSync('.env',pattern.test(text)?text.replace(pattern,()=>line):text.trimEnd()+'\n'+line+'\n',{mode:0o600});}
try{
 if(!process.env.MAX_BOT_TOKEN)throw Error('MAX_NOT_CONFIGURED');
 // Never consume updates belonging to another existing webhook integration.
 const subscriptions=await api('/subscriptions');if(subscriptions.subscriptions?.length)throw Error('MAX_EXISTING_WEBHOOK');
 if(mode==='prepare'){
  const bot=await api('/me'),code='FENIX-MANAGER-'+crypto.randomBytes(10).toString('hex').toUpperCase();
  if(!bot.user_id||!bot.username)throw Error('MAX_BOT_UNAVAILABLE');
  stateSave({botId:String(bot.user_id),username:bot.username,code,expiresAt:Date.now()+30*60000,paired:false});
  console.log('Менеджеру: откройте https://max.ru/'+bot.username+', нажмите «Начать» и отправьте одним сообщением:');
  console.log('/connect '+code);console.log('Код действует 30 минут. Текущий получатель пока не изменён.');
 }else if(mode==='confirm'){
  const state=JSON.parse(fs.readFileSync(file,'utf8'));
  if(state.paired||!state.code)throw Error('MAX_CODE_ALREADY_USED');if(Date.now()>state.expiresAt)throw Error('MAX_CODE_EXPIRED');
  const bot=await api('/me');if(String(bot.user_id)!==state.botId)throw Error('MAX_BOT_CHANGED');
  const query=new URLSearchParams({timeout:'5',limit:'100'});if(state.marker!==undefined&&state.marker!==null)query.set('marker',String(state.marker));
  const updates=await api('/updates?'+query),match=managerMessage(updates.updates,state.code);
  if(updates.marker!==undefined)state.marker=updates.marker;
  if(!match){stateSave(state);console.log('Команда менеджера пока не найдена. Повторите подтверждение после отправки.');exitCode(2);}
  else{
   const id=String(match.message.sender.user_id);if(!/^\d{1,20}$/.test(id))throw Error('MAX_RECIPIENT_INVALID');
   setEnv('MAX_USER_ID',id);setEnv('MAX_CHAT_ID','');state.userId=id;state.paired=true;delete state.code;stateSave(state);
   console.log('Личный чат менеджера подтверждён. Изменён только локальный .env; сервер REG.RU ещё не изменён.');
  }
 }else throw Error('MAX_INVALID_MODE');
}catch(error){console.error(/^MAX_[A-Z0-9_]+$/.test(error.message)?error.message:'MAX_SETUP_FAILED');process.exitCode=1;}
finally{agent.destroy();}
function exitCode(code){process.exitCode=code;}

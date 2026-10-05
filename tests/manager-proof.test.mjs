import test from 'node:test';
import assert from 'node:assert/strict';
import {managerMessage} from '../scripts/manager-proof.mjs';
test('manager pairing accepts the exact code only from a human personal dialog',()=>{
 const code='FENIX-MANAGER-1234567890ABCDEF1234';
 const update={update_type:'message_created',message:{body:{text:'/connect '+code},recipient:{chat_type:'dialog'},sender:{is_bot:false,user_id:12345}}};
 assert.equal(managerMessage([update],code),update);
 for(const change of [
  {...update,message:{...update.message,recipient:{chat_type:'chat'}}},
  {...update,message:{...update.message,sender:{is_bot:true,user_id:12345}}},
  {...update,message:{...update.message,sender:{user_id:12345}}},
  {...update,message:{...update.message,sender:{is_bot:false,user_id:'123&chat_id=9'}}},
  {...update,message:{...update.message,body:{text:'/connect WRONG'}}},
  {...update,update_type:'bot_started'}
 ])assert.equal(managerMessage([change],code),undefined);
 assert.equal(managerMessage([update],undefined),undefined);
});

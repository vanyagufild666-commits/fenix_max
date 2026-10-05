<?php
// Isolated transport and real SQLite exercise. No live MAX credentials or real contact data.
declare(strict_types=1);
$directory=$argv[1]??'';if(!is_file($directory.'/core.php'))throw new RuntimeException('Run with the isolated Node test harness');
$cfg=['DATABASE_PATH'=>'data/site.sqlite','MAX_BOT_TOKEN'=>'isolated-test-token','MAX_USER_ID'=>'12345','MAX_CHAT_ID'=>'-999'];
require $directory.'/core.php';require $directory.'/max-notifications.php';
function check(bool $value,string $message): void {if(!$value)throw new RuntimeException($message);}
try{
 query('SELECT 1');$value=['name'=>'Тестовая заявка','phone'=>'+79990000000','channel'=>'phone','requested_city'=>'Омск'];
 query('INSERT INTO leads VALUES(?,?,?,?,?,?)',['test-lead','test-idempotency','fingerprint',enc($value),'new',now()]);
 query('INSERT INTO outbox(id,lead_id,status,attempts,updated_at) VALUES(?,?,?,?,?)',['test-outbox','test-lead','pending',0,now()]);
 $calls=0;$send=function($url,$body,$headers)use(&$calls){$calls++;check(str_contains($url,'user_id=12345')&&!str_contains($url,'chat_id='),'Personal recipient');check($body['notify']===true,'Push enabled');check(str_contains($body['text'],'Телефон: +79990000000'),'Phone present');return ['message'=>['body'=>['mid'=>'max-receipt-1']]];};
 $result=dispatchMaxOne($send);check($result['sent']===1,'Delivered');check(dispatchMaxOne($send)['sent']===0&&$calls===1,'No duplicate');
 query("UPDATE outbox SET status='pending',attempts=0 WHERE id='test-outbox'");
 $failed=dispatchMaxOne(fn()=>throw new RuntimeException('Provider offline'));check($failed['status']===502,'Provider failure');check(query('SELECT status FROM outbox')->fetchColumn()==='failed','Durable retry');
 check(dispatchMaxOne($send)['sent']===0,'No immediate retry storm');query('UPDATE outbox SET updated_at=?',[gmdate('Y-m-d\TH:i:s.000\Z',time()-65)]);
 check(dispatchMaxOne($send)['sent']===1,'Retry delivered');
 query("UPDATE outbox SET status='pending',attempts=0");$unconfirmed=dispatchMaxOne(fn()=>['success'=>true]);check($unconfirmed['status']===502,'Success needs a message receipt');
 query("UPDATE outbox SET status='failed',attempts=5,updated_at=?",[gmdate('Y-m-d\TH:i:s.000\Z',time()-65)]);check(dispatchMaxOne($send)['sent']===0,'Retries bounded');
 echo "MAX delivery tests passed: private recipient, push, durable retry, no duplicate and confirmed receipt.\n";
}finally{/* The test harness removes its temporary database after this PHP process closes it. */}

<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
chdir(dirname(__DIR__));
$cfg=[];
if(is_file('.env'))foreach(file('.env',FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES) as $line){
 $line=trim($line);if($line===''||str_starts_with($line,'#')||!str_contains($line,'='))continue;
 [$key,$value]=explode('=',$line,2);$key=trim($key);$value=trim($value);
 if(!preg_match('/^[A-Z_]+$/D',$key))continue;
 if(strlen($value)>1&&(($value[0]==='"'&&str_ends_with($value,'"'))||($value[0]==="'"&&str_ends_with($value,"'"))))$value=substr($value,1,-1);
 $cfg[$key]=$value;
}
// Resolve relative local storage from the bot folder, never the process launch directory.
if(isset($cfg['DATABASE_PATH'])&&!str_starts_with($cfg['DATABASE_PATH'],'/')&&!preg_match('~^[a-zA-Z]:[\\\\/]~',$cfg['DATABASE_PATH']))$cfg['DATABASE_PATH']=getcwd().'/'.$cfg['DATABASE_PATH'];
require __DIR__.'/core.php';require __DIR__.'/max-notifications.php';
try{
 if(!maxConfigured())throw new RuntimeException('MAX_NOT_CONFIGURED');maxRecipient();
 $mode=$argv[1]??'--once';if(!in_array($mode,['--once','--loop','--check'],true))throw new RuntimeException('INVALID_MODE');
 if($mode==='--check'){
  $c=curl_init('https://platform-api2.max.ru/me');curl_setopt_array($c,[CURLOPT_HTTPHEADER=>['Authorization: '.config('MAX_BOT_TOKEN')],CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>15,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_CAINFO=>__DIR__.'/certs/russian-trusted-root-ca.pem']);
  $body=curl_exec($c);$status=(int)curl_getinfo($c,CURLINFO_HTTP_CODE);curl_close($c);$bot=json_decode($body?:'{}',true);
  if($status!==200||empty($bot['user_id']))throw new RuntimeException('MAX_CHECK_FAILED');
  echo enc(['connected'=>true,'bot'=>$bot['username']??'','recipient'=>config('MAX_USER_ID')!==''?'personal':'group'])."\n";exit;
 }
 db();$lock=fopen(dirname(databasePath()).'/max-worker.lock','c');if(!$lock||!flock($lock,LOCK_EX|LOCK_NB)){echo "Worker already running.\n";exit;}
 do{
  $sent=0;for($i=0;$i<20;$i++){$result=dispatchMaxOne();if(($result['sent']??0)!==1){if(isset($result['error']))fwrite(STDERR,"MAX delivery deferred; lead remains in queue.\n");break;}$sent++;usleep(550000);}
  echo enc(['sent'=>$sent,'time'=>now()])."\n";
  if($mode==='--loop')sleep(60);
 }while($mode==='--loop');
}catch(Throwable){fwrite(STDERR,"Bot operation failed. Check private configuration, PHP extensions and connectivity.\n");exit(1);}

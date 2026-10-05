<?php
declare(strict_types=1);
function maxConfigured(): bool {return config('MAX_BOT_TOKEN')!==''&&(config('MAX_USER_ID')!==''||config('MAX_CHAT_ID')!=='');}
function maxRecipient(): string {
 $user=config('MAX_USER_ID');if($user!==''){if(!preg_match('/^\d{1,20}$/D',$user))throw new RuntimeException('MAX_RECIPIENT_INVALID');return 'user_id='.rawurlencode($user);}
 $chat=config('MAX_CHAT_ID');if(!preg_match('/^-?\d{1,20}$/D',$chat))throw new RuntimeException('MAX_RECIPIENT_INVALID');return 'chat_id='.rawurlencode($chat);
}
function maxLine(mixed $value,string $fallback): string {return is_string($value)?(trim(preg_replace('/[\r\n\t]+/',' ',$value))?:$fallback):$fallback;}
function maxLeadNotice(string $id,array $lead): array {
 $channel=['phone'=>'По телефону','max'=>'В MAX'][$lead['channel']??'phone']??'По телефону';
 return ['text'=>"Новая заявка с сайта — Феникс Плюс\n\nИмя: ".maxLine($lead['name']??'','не указано')."\nТелефон: ".maxLine($lead['phone']??'','не указан')."\nГород: ".maxLine($lead['requested_city']??'','не указан')."\nКак связаться: $channel\nНужен трансфер: ".(!empty($lead['needs_transfer'])?'да':'нет')."\nСтраница обращения: ".maxLine($lead['landing_url']??'','/')."\n\nID заявки: $id",'notify'=>true];
}
// The claim is committed before the external request. Both the shutdown handler and cron use this relay.
function dispatchMaxOne(?callable $send=null,string $onlyLead=''): array {
 if(!maxConfigured())return ['sent'=>0,'error'=>'MAX_NOT_CONFIGURED','status'=>503];
 $row=immediate(function()use($onlyLead){$where=$onlyLead!==''?' AND lead_id=?':'';$args=[gmdate('Y-m-d\TH:i:s.000\Z',time()-60),gmdate('Y-m-d\TH:i:s.000\Z',time()-120)];if($onlyLead!=='')$args[]=$onlyLead;
  $row=query("SELECT id,lead_id FROM outbox WHERE (status='pending' OR (status='failed' AND attempts<5 AND updated_at<?) OR (status='sending' AND updated_at<?))".$where.' ORDER BY updated_at LIMIT 1',$args)->fetch();
  if($row)query("UPDATE outbox SET status='sending',attempts=attempts+1,updated_at=? WHERE id=?",[now(),$row['id']]);return $row;
 });if(!$row)return ['sent'=>0];
 try{
  $value=query('SELECT value FROM leads WHERE id=?',[$row['lead_id']])->fetchColumn();if(!$value)throw new RuntimeException('LEAD_UNAVAILABLE');
  $url='https://platform-api2.max.ru/messages?'.maxRecipient().'&disable_link_preview=true';$notice=maxLeadNotice($row['lead_id'],json_decode($value,true));
  $send ??= fn($url,$notice,$headers)=>remoteJson($url,$notice,$headers);
  $result=$send($url,$notice,['Authorization: '.config('MAX_BOT_TOKEN')]);
  if(empty($result['message']['body']['mid']))throw new RuntimeException('DELIVERY_NOT_CONFIRMED');
  query("UPDATE outbox SET status='sent',last_error=NULL,updated_at=? WHERE id=?",[now(),$row['id']]);return ['sent'=>1,'messageId'=>$result['message']['body']['mid']];
 }catch(Throwable){query("UPDATE outbox SET status='failed',last_error='DELIVERY_FAILED',updated_at=? WHERE id=?",[now(),$row['id']]);return ['sent'=>0,'error'=>'MAX временно недоступен. Заявка сохранена в очереди.','status'=>502];}
}
function notifyMaxAfterResponse(string $id): void {
 if(!maxConfigured())return;
 register_shutdown_function(function()use($id){if(function_exists('fastcgi_finish_request'))fastcgi_finish_request();try{dispatchMaxOne(null,$id);}catch(Throwable){error_log('MAX_DELIVERY_DEFERRED');}});
}

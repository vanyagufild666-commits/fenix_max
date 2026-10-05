export function managerMessage(updates,code){
 if(typeof code!=='string'||!/^FENIX-MANAGER-[A-F0-9]{20}$/.test(code))return undefined;
 return updates?.find(update=>update.update_type==='message_created'&&update.message?.body?.text?.trim()==='/connect '+code&&update.message?.recipient?.chat_type==='dialog'&&update.message?.sender?.is_bot===false&&/^\d{1,20}$/.test(String(update.message.sender.user_id)));
}

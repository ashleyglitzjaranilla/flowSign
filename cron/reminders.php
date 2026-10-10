<?php
require dirname(__DIR__).'/app/bootstrap.php';
if(PHP_SAPI!=='cli')exit("CLI only\n");
$hours=max(1,(int)cfg('REMINDER_HOURS','24'));
$rows=q("SELECT d.id,d.reference,d.version,d.head_id FROM documents d WHERE d.status='pending' AND d.updated_at < DATE_SUB(NOW(), INTERVAL $hours HOUR)")->fetchAll();
foreach($rows as $d){$key='reminder_'.$d['version'];$last=q('SELECT created_at FROM email_logs WHERE document_id=? AND user_id=? AND event_key=? ORDER BY id DESC LIMIT 1',[$d['id'],$d['head_id'],$key])->fetch();if($last && strtotime($last['created_at'])>time()-$hours*3600)continue;notify_user((int)$d['head_id'],(int)$d['id'],$key,'Reminder: '.$d['reference'].' is awaiting your signature');echo 'Processed '.$d['reference']."\n";}

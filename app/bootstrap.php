<?php
declare(strict_types=1);
$base=dirname(__DIR__);
$sessionPath=$base.'/storage/sessions';
if(!is_dir($sessionPath)&&!mkdir($sessionPath,0700,true)&&!is_dir($sessionPath))throw new RuntimeException('Could not create the session storage directory.');
session_save_path($sessionPath);
session_start();
if(is_file($base.'/vendor/autoload.php')) require_once $base.'/vendor/autoload.php';
if(!is_file($base.'/.env')) { if(PHP_SAPI!=='cli') die('Copy .env.example to .env and configure MySQL. See README.md'); throw new RuntimeException('Missing .env'); }
foreach(file($base.'/.env',FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES) as $line){$line=trim($line);if($line===''||$line[0]==='#'||!str_contains($line,'='))continue;[$k,$v]=explode('=',$line,2);$_ENV[trim($k)]=trim(trim($v),'"\'');}
function cfg(string $key,string $default=''):string{return $_ENV[$key]??$default;}
date_default_timezone_set(cfg('APP_TIMEZONE','Asia/Singapore'));
function formatted_date(?string $value):string{if(!$value)return '';return date('F j, Y',strtotime($value));}
function formatted_time(?string $value):string{if(!$value)return '';return strtolower(date('g:i a',strtotime($value)));}
function db():PDO{static $db=null;if($db)return $db;$dsn='mysql:host='.cfg('DB_HOST','127.0.0.1').';port='.cfg('DB_PORT','3306').';dbname='.cfg('DB_NAME','signflow').';charset=utf8mb4';return $db=new PDO($dsn,cfg('DB_USER','root'),cfg('DB_PASS'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);}
function q(string $sql,array $params=[]):PDOStatement{$s=db()->prepare($sql);$s->execute($params);return $s;}
function h(?string $s):string{return htmlspecialchars($s??'',ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
function me():?array{if(empty($_SESSION['uid']))return null;$u=q('SELECT id,name,email,role,department,signature_path FROM users WHERE id=?',[(int)$_SESSION['uid']])->fetch();return $u?:null;}
function go(string $url):never{header('Location: '.$url);exit;}
function require_login():array{$u=me();if(!$u)go('/?page=login');return $u;}
function csrf():string{return $_SESSION['csrf']??=bin2hex(random_bytes(32));}
function verify_csrf():void{if(!hash_equals(csrf(),(string)($_POST['csrf']??'')))throw new RuntimeException('Security token expired. Reload and retry.');}
function flash(string $msg):void{$_SESSION['flash']=$msg;}
function action(string $name,int $id=0):void{q('INSERT INTO approval_history(document_id,actor_id,action) VALUES(?,?,?)',[$id,(int)$_SESSION['uid'],$name]);}
function notify_user(int $uid,int $doc,string $event,string $subject):void{q('INSERT INTO notifications(user_id,document_id,subject) VALUES(?,?,?)',[$uid,$doc,$subject]);send_email($uid,$doc,$event,$subject);}
function send_email(int $uid,int $doc,string $event,string $subject):void{
 $u=q('SELECT email,name FROM users WHERE id=?',[$uid])->fetch();$status='disabled';$details='SMTP not configured';
 if(cfg('MAIL_USER')!==''&&cfg('MAIL_PASSWORD')!==''&&class_exists('PHPMailer\\PHPMailer\\PHPMailer')){
 try{$m=new PHPMailer\PHPMailer\PHPMailer(true);$m->isSMTP();$m->Host=cfg('MAIL_HOST','smtp.gmail.com');$m->SMTPAuth=true;$m->Username=cfg('MAIL_USER');$m->Password=cfg('MAIL_PASSWORD');$m->SMTPSecure=PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;$m->Port=(int)cfg('MAIL_PORT','587');$m->CharSet='UTF-8';$m->setFrom(cfg('MAIL_FROM',cfg('MAIL_USER')),cfg('MAIL_FROM_NAME','SignFlow'));$m->addAddress($u['email'],$u['name']);$m->isHTML(true);$m->Subject=$subject;$url=rtrim(cfg('APP_URL','http://localhost:8000'),'/').'/?page=view&id='.$doc;$m->Body='<p>Hello '.h($u['name']).',</p><p>'.h($subject).'</p><p><a href="'.h($url).'">Review document securely</a></p><p>Please log in before signing.</p>';$m->AltBody=$subject.' Open: '.$url;$m->send();$status='sent';$details='Accepted by SMTP server';}catch(Throwable $e){$status='failed';$details=substr($e->getMessage(),0,500);}
 }
 q('INSERT INTO email_logs(document_id,user_id,event_key,status,details) VALUES(?,?,?,?,?)',[$doc,$uid,$event,$status,$details]);
}
function save_signature(string $data,int $doc,int $uid,int $version,string $kind,array $placement=[]):void{
 if(!preg_match('~^data:image/png;base64,([A-Za-z0-9+/=]+)$~',$data,$m))throw new RuntimeException('Draw your signature first.');$bytes=base64_decode($m[1],true);if($bytes===false||strlen($bytes)>250000||strlen($bytes)<100||substr($bytes,0,8)!=="\x89PNG\r\n\x1a\n")throw new RuntimeException('Invalid signature image.');
 $page=max(1,(int)($placement['page_num']??1));$x=(float)($placement['x_pct']??10);$y=(float)($placement['y_pct']??75);$w=(float)($placement['width_pct']??28);$h=(float)($placement['height_pct']??10);if($x<0||$y<0||$w<5||$h<3||$x+$w>100.01||$y+$h>100.01)throw new RuntimeException('Invalid signature placement.');
 $path=dirname(__DIR__).'/storage/signatures';if(!is_dir($path))mkdir($path,0700,true);$file=$doc.'_'.$uid.'_'.$version.'_'.$kind.'_'.bin2hex(random_bytes(6)).'.png';if(file_put_contents($path.'/'.$file,$bytes)===false)throw new RuntimeException('Could not save signature.');q('INSERT INTO signatures(document_id,user_id,version,kind,file_path,page_num,x_pct,y_pct,width_pct,height_pct) VALUES(?,?,?,?,?,?,?,?,?,?)',[$doc,$uid,$version,$kind,'signatures/'.$file,$page,$x,$y,$w,$h]);
}
function save_uploaded_pdf(array $upload):array{
 if(($upload['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK)throw new RuntimeException('Choose a PDF file to upload.');
 $size=(int)($upload['size']??0);if($size<5||$size>10*1024*1024)throw new RuntimeException('PDF must be no larger than 10 MB.');
 $tmp=(string)($upload['tmp_name']??'');if(!is_uploaded_file($tmp))throw new RuntimeException('Invalid file upload.');
 $fh=fopen($tmp,'rb');$magic=$fh?fread($fh,5):'';if($fh)fclose($fh);
 $mime=(new finfo(FILEINFO_MIME_TYPE))->file($tmp);if($magic!=='%PDF-'||!in_array($mime,['application/pdf','application/x-pdf'],true))throw new RuntimeException('Only valid PDF files are accepted.');
 $dir=dirname(__DIR__).'/storage/uploads';if(!is_dir($dir)&&!mkdir($dir,0700,true)&&!is_dir($dir))throw new RuntimeException('Could not create upload storage.');
 $file=bin2hex(random_bytes(20)).'.pdf';if(!move_uploaded_file($tmp,$dir.'/'.$file))throw new RuntimeException('Could not store uploaded PDF.');
 $name=trim((string)($upload['name']??'document.pdf'));$name=preg_replace('/[^\pL\pN._ -]+/u','_',$name)?:'document.pdf';
 return ['path'=>'uploads/'.$file,'name'=>mb_substr($name,0,255)];
}
function save_profile_signature(array $upload,int $uid):string{
 if(($upload['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK)throw new RuntimeException('Choose a signature image.');
 $size=(int)($upload['size']??0);if($size<50||$size>2*1024*1024)throw new RuntimeException('Signature image must be no larger than 2 MB.');
 $tmp=(string)($upload['tmp_name']??'');if(!is_uploaded_file($tmp))throw new RuntimeException('Invalid image upload.');
 $mime=(new finfo(FILEINFO_MIME_TYPE))->file($tmp);$extensions=['image/png'=>'png','image/jpeg'=>'jpg'];if(!isset($extensions[$mime])||@getimagesize($tmp)===false)throw new RuntimeException('Upload a valid PNG or JPEG signature image.');
 $dir=dirname(__DIR__).'/storage/profile-signatures';if(!is_dir($dir)&&!mkdir($dir,0700,true)&&!is_dir($dir))throw new RuntimeException('Could not create signature storage.');
 $file=$uid.'_'.bin2hex(random_bytes(16)).'.'.$extensions[$mime];if(!move_uploaded_file($tmp,$dir.'/'.$file))throw new RuntimeException('Could not store signature image.');
 return 'profile-signatures/'.$file;
}
function signature(int $doc,int $version,string $kind):?string{$r=q('SELECT file_path FROM signatures WHERE document_id=? AND version=? AND kind=? ORDER BY id DESC LIMIT 1',[$doc,$version,$kind])->fetch();return $r?$r['file_path']:null;}
function signature_record(int $doc,int $version,string $kind):?array{$r=q('SELECT * FROM signatures WHERE document_id=? AND version=? AND kind=? ORDER BY id DESC LIMIT 1',[$doc,$version,$kind])->fetch();return $r?:null;}
function delete_storage_file(?string $relative):void{if(!$relative)return;$root=realpath(dirname(__DIR__).'/storage');$file=realpath(dirname(__DIR__).'/storage/'.$relative);if($root&&$file&&str_starts_with(strtolower($file),strtolower($root.DIRECTORY_SEPARATOR))&&is_file($file))unlink($file);}
function document(int $id):array{$d=q('SELECT d.*,e.name employee_name,e.department,h.name head_name FROM documents d JOIN users e ON e.id=d.employee_id JOIN users h ON h.id=d.head_id WHERE d.id=?',[$id])->fetch();if(!$d)throw new RuntimeException('Document not found.');return $d;}
function permitted(array $d,array $u):bool{return $u['role']==='admin'||(int)$d['employee_id']===(int)$u['id']||(int)$d['head_id']===(int)$u['id'];}

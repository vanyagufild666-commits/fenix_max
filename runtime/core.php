<?php
declare(strict_types=1);
function config(string $key): string {global $cfg;return (string)($cfg[$key]??getenv($key)?:'');}
function now(): string {return gmdate('Y-m-d\TH:i:s.000\Z');}
function enc(mixed $value): string {return json_encode($value,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);}
function databasePath(): string {
 $path=config('DATABASE_PATH')?:'../data/site.sqlite';
 return str_starts_with($path,'/')||preg_match('~^[a-zA-Z]:[\\\\/]~',$path)?$path:__DIR__.'/'.$path;
}
function db(): PDO {
 static $db;if($db)return $db;
 $file=databasePath();if(!is_dir(dirname($file))&&!mkdir(dirname($file),0700,true))throw new RuntimeException('STORAGE_UNAVAILABLE');
 $db=new PDO('sqlite:'.$file,null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
 $db->exec('PRAGMA foreign_keys=ON; PRAGMA busy_timeout=5000; PRAGMA journal_mode=WAL;');
 $db->exec(file_get_contents(__DIR__.'/migration.sql'));return $db;
}
function query(string $sql,array $args=[]): PDOStatement {$q=db()->prepare($sql);$q->execute($args);return $q;}
function immediate(callable $callback): mixed {
 db()->exec('BEGIN IMMEDIATE');try{$value=$callback();db()->exec('COMMIT');return $value;}catch(Throwable $error){db()->exec('ROLLBACK');throw $error;}
}
function remoteJson(string $url,array $data,array $headers,int $timeout=10): array {
 $curl=curl_init($url);curl_setopt_array($curl,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>enc($data),CURLOPT_HTTPHEADER=>array_merge(['Content-Type: application/json'],$headers),CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_TIMEOUT=>$timeout,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_FOLLOWLOCATION=>false]);
 if(parse_url($url,PHP_URL_HOST)==='platform-api2.max.ru')curl_setopt($curl,CURLOPT_CAINFO,__DIR__.'/certs/russian-trusted-root-ca.pem');
 $body=curl_exec($curl);$status=(int)curl_getinfo($curl,CURLINFO_HTTP_CODE);curl_close($curl);
 if($body===false||$status<200||$status>=300)throw new RuntimeException('PROVIDER_UNAVAILABLE');return json_decode($body,true,64,JSON_THROW_ON_ERROR);
}

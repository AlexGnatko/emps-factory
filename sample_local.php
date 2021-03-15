<?php
// MAIN CONFIGURATION SCRIPT
// PLEASE REFER /modules/_common/config FOR MORE CONFIGURATION SCRIPTS

// hostname / URL configuration
if(!defined('EMPS_HOST_NAME')){
	define('EMPS_HOST_NAME','factory.somehost.com');
}

$emps_force_hostname = false;

define('EMPS_SCRIPT_WEB','http://'.EMPS_HOST_NAME);
define('EMPS_SCRIPT_URL_FOLDER','');

// file paths configuration
define('EMPS_SCRIPT_PATH','/srv/www/emps-factory/htdocs');
define('EMPS_INCLUDE_PATH','/srv/www/lib'); // always have the include paths separated by : (even on Windows)

if(!defined('EMPS_WEBSITE_SCRIPT_PATH')){
	define('EMPS_WEBSITE_SCRIPT_PATH',EMPS_SCRIPT_PATH);
}

// timezone correction configuration
define('EMPS_TZ_CORRECT',0);
define('EMPS_TZ','Asia/Irkutsk');

date_default_timezone_set(EMPS_TZ);

define('EMPS_DT_FORMAT','%d.%m.%Y %H:%M');

define('EMPS_UPLOAD_SUBFOLDER','/local/upload/');

define('EMPS_MIN_WATERMARKED', 600);

// script timing configuration
define('EMPS_TIMING',false);
define('EMPS_SHOW_TIMING',false);
define('EMPS_SHOW_SQL_ERRORS',false);

// session cookie parameters
define('EMPS_SESSION_COOKIE_LIFETIME',3600*24*7);

define('EMPS_DISPLAY_ERRORS',1);

define('CURRENT_LANG',1);
define('PHOTOSET_WATERMARK',false);

define('EMPS_PHOTO_SIZE','1200x1200|100x100|inner');

define("EMPS_FONTS","/srv/www/fonts");

$emps_custom_session_handler = true;

// database configuration. This object will be destroyed upon connection to the database for security reasons.
$emps_db_config = array(
	'host' => 'localhost',
	'database' => 'user_emps_factory',
	'user' => 'emps_factory_user',
	'password' => 'passW0rd',
	'charset' => 'utf8');

define('TP','c_');	// table name prefix

// URL variable tracking configuration
// Variables watch list
define('EMPS_VARS','aact,pp,act,key,t,ss,start,start2,start3,start4,sk,dlist,sd,sm,cmd,sx,sy,sz');

// Variable/Path mapping string. Variables listed in the order that is used
// to retrieve them from URLs.
define('EMPS_URL_VARS','pp,key,start,ss,sd,sk,sm,sx,sy');

// Don't start sessions on these modules
define('EMPS_NO_SESSION','pic,thumb,freepic,heartbeat,sendmail,geocode,instagram-pull,weather-update,pubs-relations,pubs-fire,afisha-kp,service-backup,banner-click,banner-show');

define('EMPS_FAST', EMPS_NO_SESSION);

// language configuration
$emps_lang = 'en';								// default language setting
$emps_lang_map=array('' => 'nn', 'en' => 'en');		// subdomain mapping for language settings

// configuration of SMTP email box for sending messages from the website
$emps_default_smtp_params=array(
	'From' => 'info@ag38.ru',
	'Reply-To' => 'info@ag38.ru',
	'Content-Type' => 'text/html; charset=utf-8',
);

$emps_default_smtp_data=array(
	'host' => 'shadowburner.org',
	'port' => '25',
	'auth' => true,
	'username' => 'info@ag38.ru',
	'password' => 'emailpasSW0rd',
);

// initialize the global SMTP vars with the default values
$emps_smtp_params=$emps_default_smtp_params;
$emps_smtp_data=$emps_default_smtp_data;

// OAUTH CREDENTIALS

if($emps_worker_mode){
// Override DB config, worker should be using MySQL root
$emps_db_config = array(
	'host' => 'localhost',
	'database' => 'user_emps_factory',
	'user' => 'root',
	'password' => 'rootPassW0rd',
	'charset' => 'utf8');
}


<?php
// initialize the spool directory and authorization
set_magic_quotes_runtime(0);

// data
require_once("config.php");
require_once("funcs.php");

// derived data
$iMaxSize = returnBytes($maxSize);
$tDbPath = $spoolDir . "/data.db";
$uDbPath = $spoolDir . "/user.db";
$Path = $spoolDir . "/data.db";
$dataDir = $spoolDir . "/data";

// initialize the dbs
$dbMode = (version_compare(PHP_VERSION, "4.3.5", "<")? "w": "c");
$tDb = dba_popen($tDbPath, $dbMode, $dbHandler) or die();
$uDb = dba_popen($uDbPath, $dbMode, $dbHandler) or die();

// expire tickets
for($key = dba_firstkey($tDb); $key; $key = dba_nextkey($tDb))
{
  $DATA = dba_fetch($key, $tDb);
  if($DATA === false) continue;
  $DATA = unserialize($DATA);
  if(
      ($DATA["expire"] && $DATA["expire"] < time()) ||
      ($DATA["expireLast"] && $DATA["lastTime"] &&
	  ($DATA["expireLast"] + $DATA["lastTime"]) < time()) ||
      ($DATA["expireDln"] && $DATA["downloads"] >= $DATA["expireDln"])
     )
    purgeDl($key, $DATA);
}


// authorization
function authenticate()
{
  global $uDb;

  // external authentication (built-in methods)
  foreach(Array('PHP_AUTH_USER', 'REMOTE_USER', 'REDIRECT_REMOTE_USER') as $key)
  {
    if(isset($_SERVER[$key]))
    {
      $remoteUser = $_SERVER[$key];
      break;
    }
  }

  // external authentication (external methods)
  if(!isset($remoteUser))
  {
    foreach(Array('REMOTE_AUTHORIZATION', 'REDIRECT_REMOTE_AUTHORIZATION') as $key)
    {
      if(isset($_SERVER[$key]))
      {
	list($remoteUser) = explode(':', base64_decode(substr($_SERVER[$key], 6)));
	break;
      }
    }
  }

  // authentication attempt
  if(isset($remoteUser))
    $user = $remoteUser;
  else
  {
    if(empty($_REQUEST['u']) || !isset($_REQUEST['p']))
      return false;

    $user = $_REQUEST['u'];
    $pass = md5($_REQUEST['p']);
  }

  // verify if we have administration rights
  $DATA = dba_fetch($user, $uDb);
  if($DATA === false)
  {
    $okpass = isset($remoteUser);
    $admin = false;
  }
  else
  {
    $DATA = unserialize($DATA);
    $okpass = (isset($remoteUser) || ($pass === $DATA['pass']));
    $admin = $DATA['admin'];
  }

  if(!$okpass) return false;
  return array('user' => $user, 'admin' => $admin);
}

session_name($sessionName);
session_start();
if(!isset($_SESSION["auth"]) || isset($_REQUEST['u']))
  $_SESSION["auth"] = authenticate();
$auth = &$_SESSION["auth"];

?>

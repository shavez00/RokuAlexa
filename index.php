<?php
include 'validator.php';
include_once 'core.php';
include 'network.info';

$developerSetting = true;
$path = ltrim($_SERVER['REQUEST_URI'], '/'); // Trim leading slash(es) 
$elements = explode('/', $path);  // Split path on slashes 

foreach ($elements as $element) {
    $element = validator::testInput($element);
}

$devices = array('roku');

if(!in_array($elements[0], $devices)) { // No path elements means home 
  include 'index.html';
  exit;
}

switch ($elements[0]) {
    case 'roku':
       try { 
            $msg = getallheaders();
            if ($msg['Authorization'] == 'password') { 
                //need to change from hard coded serial number to dynmically inputed from request
                $roku = new Roku("YW00A8650087");
                $url = $roku->$elements[1]($elements[2]);
           } else {
               echo "not authorized";
               die;
            }
        } catch (Exception $e) {
            if($developerSetting == true) echo 'Caught exception ' . $e->getMessage() . "\n";
            $errorMessage = date(DATE_RFC2822) . ' ' . $e->getMessage() . "\n";
            $rokuUri = file_put_contents('/var/log/roku/error.log', $errorMessage, FILE_APPEND);
        }
        //$roku = new Roku();
        break;
    default:
        include 'index.html';
}
?>

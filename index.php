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
                $roku = new Roku();
                $url = $roku->$elements[1]($elements[2]);
           } else {
               // header("Location: http://www.google.com/");
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

//var_dump($elements);

/*else switch(array_shift($elements)) // Pop off first item and switch 
{ 
    case 'roku': 
        ShowPicture($elements); // passes rest of parameters to internal function 
        break; 
    case 'more': 
        ... 
    default: 
        header('HTTP/1.1 404 Not Found'); 
        Show404Error(); 
}*/
?>

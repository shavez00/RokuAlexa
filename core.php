<?php
set_include_path( get_include_path().PATH_SEPARATOR.__DIR__ . "/cfg" . PATH_SEPARATOR.__DIR__ .  "/aps" . PATH_SEPARATOR); //Need to set include path to include current directory

function autoload($class) 

{

$paths = explode(PATH_SEPARATOR, get_include_path());

$flags = PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE;

$file = strtolower(str_replace("\\", DIRECTORY_SEPARATOR, trim($class, "\\"))).".php"; //need to set name of php file to lowercase because of stringtolower command

	 foreach ($paths as $path) {

		 $combined = $path.DIRECTORY_SEPARATOR.$file;

		 if (file_exists($combined)) {

			 /*echo '<br>'.$combined.'<br>'; //Troubleshooting code to echo out the file that's being loaded

			 exit;*/

		 	include($combined);

			 return;

}

}

throw new Exception("{$class} not found");

}

class Autoloader 

{

public static function autoload($class) {

		 autoload($class);

}

}

spl_autoload_register('autoload');

spl_autoload_register(array('autoloader', 'autoload'));

// these can only be called within a class context…

//spl_autoload_register(array($this, 'autoload'));

//spl_autoload_register(__CLASS__.'::load');



?>
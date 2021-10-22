<?php

//https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-4-autoloader-examples.md
spl_autoload_register(function ($class) {

    $prefix = 'Innokassa\\';
    $base_dir = __DIR__."/";

    // does the class use the namespace prefix?
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        // no, move to the next registered autoloader
        return;
    }

    $relative_class = substr($class, $len);

    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    //echo $file."\n";
    if (file_exists($file)) {
        require $file;
    }
});

//##########################################################################

function print_exception($e, $stacktrace=false, $return=false): ?string
{
    $sError = $e->getCode()." => ".$e->getMessage()."\n";

    if($stacktrace)
        $sError .= "Stack trace:\n".$e->getTraceAsString()."\n";

    if($return)
        return $sError;
    
    echo $sError;
    return null;
}

function exit_print_r($data)
{
	header("Content-type: text/plain; charset: utf-8");
	print_r($data);
	exit();
}

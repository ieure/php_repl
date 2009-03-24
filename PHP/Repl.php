<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * A REPL for PHP
 *
 * PHP version 5
 *
 * @category   PHP
 * @package    Repl
 * @author     Ian Eure <ieure@php.net>
 * @copyright  2009 Ian Eure.
 * @filesource
 */

$prompt = "php> ";
$stdin  = fopen('php://stdin', 'r');
$code = 'null';
do {
    if (substr($code, -1) != ';') {
        $code .= ';';
    }
    if (strpos($code, 'return') !== 0) {
        $code = 'return ' . $code;
    }

    /* echo $code . "\n"; */
    $res = eval($code);
    var_dump($res);
    echo $prompt;
} while ($code = trim(fgets($stdin)));

?>

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
$__stdin__  = fopen('php://stdin', 'r');
$__code__ = '"PHP_Repl v0.1"';
do {
    if (substr($__code__, -1) != ';') {
        $__code__ .= ';';
    }
    if (strpos($__code__, 'return') !== 0) {
        $__code__ = 'return ' . $__code__;
    }

    $res = eval($__code__);
    var_dump($res);
    echo $prompt;
} while ($__code__ = trim(fgets($__stdin__)));

?>

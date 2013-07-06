#!/usr/bin/env php
<?php // -*- mode: php -*-

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Wrapper script for invoking PHP_Repl
 *
 * PHP version 5
 *
 * @category   PHP
 * @package    Repl
 * @author     Ian Eure <ieure@php.net>
 * @copyright  2009 Ian Eure.
 * @version    Release: $Id$
 * @filesource
 */

// Add environment variable PHP_INCLUDE_PATH to include_path if it exists.
$includePath = getenv('PHP_INCLUDE_PATH');
if ($includePath !== false) {
    set_include_path(get_include_path().':'.getenv('PHP_INCLUDE_PATH'));
}

require_once __DIR__ . '/../src/PHPRepl/PHPRepl.php';
$__repl__ = new \PHPRepl\PHPRepl();
$__repl__->run();

?>

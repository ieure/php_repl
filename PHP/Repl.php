#! @php_bin@
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

/**
 * PHP_Repl
 *
 * @package    PHP_Repl
 * @author     Ian Eure <ieure@php.net>
 * @version    @package_version@
 */
class PHP_Repl
{
    /**
     * Where we're reading input from
     *
     * @var resource
     */
    private $input;

    /**
     * The prompt
     *
     * @var string
     */
    public $prompt = 'php> ';

    /**
     * Default options for new instances
     *
     * @var array
     */
    private static $default_opts = array('autorun' => false);

    /**
     * The options for this instance
     *
     * @var array
     */
    private $options = array();


    /**
     * Constructor
     *
     * @return void
     */
    public function __construct($options = array())
    {
        $this->input = fopen('php://stdin', 'r');
        $this->options = array_merge(self::$default_opts, $options);
        if ($this->options['autorun']) {
            $this->run();
        }
    }

    /**
     * Destructor
     *
     * @return void
     */
    public function __destruct()
    {
        fclose($this->input);
    }

    /**
     * Run the main loop
     *
     * @return void
     */
    public function run()
    {
        echo $this->prompt;
        while ($__code__ = fgets($this->input)) {
            try {
                $this->_print($this->_eval($__code__));
            } catch (Exception $e) {
                echo $e . "\n";
            }
            echo $this->prompt;
        }
    }

    /**
     * Evaluate code
     *
     * @param string $_repl_code The code to eval
     *
     * @return mixed The output
     */
    private function _eval($_repl_code)
    {
        $_repl_code = trim($_repl_code);

        // Add a trailing semicolon
        if (substr($_repl_code, -1) != ';') {
            $_repl_code .= ';';
        }

        // Make sure we get a value back from eval()
        if (strpos($_repl_code, 'return') !== 0 &&
            strpos($_repl_code, 'throw') !== 0) {
            $_repl_code = 'return ' . $_repl_code;
        }

        ob_start(array($this, 'ob_cleanup'));
        $out = null;
        try {
            error_reporting(E_ALL | E_STRICT);
            ini_set('html_errors', 'Off');
            ini_set('display_errors', 'On');
            $out = eval($_repl_code);
        } catch (Exception $e) {
            // Clean up
            ob_flush();
            ob_end_clean();
            throw $e;
        }
        ob_flush();
        ob_end_clean();
        return $out;
    }

    /**
     * Clean up output captured from eval()'d code
     *
     * @param string $output Output from the code
     *
     * @return string Cleaned up output
     */
    public function ob_cleanup($output)
    {
        if (strlen($output) > 0 && substr($output, -1) != "\n") {
            $output .= "\n";
        }
        return $output;
    }

    /**
     * Print the output of some code
     *
     * @param mixed $out The output
     *
     * @return void
     */
    private function _print($out)
    {
        $type = gettype($out);
        switch ($type) {
        case 'string':
            printf("\"%s\"\n", $out);
            break;
        case 'integer':
        case 'boolean':
            var_dump($out);
        break;

        case 'array':
            var_export($out);
            echo "\n";
            break;

        default:
            print_r($out);
        }
    }
}

// If we're being run as a script, run the REPL.
if (php_sapi_name() == 'cli' &&
    basename($_SERVER['argv'][0]) == basename(__FILE__)) {
    $_repl = new PHP_Repl(array('autorun' => true));
}

?>

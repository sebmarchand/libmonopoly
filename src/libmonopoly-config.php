<?php
	define("CONFIG_DEFAULT_FILENAME", "config.ini");

        function get_config($filename = CONFIG_DEFAULT_FILENAME) {
		if (!is_readable($filename)) {
			die("Can't read config file $filename".PHP_EOL);
		}

		return parse_ini_file($filename, true);
        }
?>

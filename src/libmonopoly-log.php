<?php
	require_once("libmonopoly-config.php");

	function log_event($category, $str) {
		$config = get_config();
		file_put_contents($config['log']['filename'], date("r")." - ".str_pad('['.$category.']', $config['log']['category_pad_len']).' '.$str.PHP_EOL, FILE_APPEND | LOCK_EX);
	}


?>

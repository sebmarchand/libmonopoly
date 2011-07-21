<?php
	require_once("./Monopoly.php");
	
	$monopoly_config = new MonopolyConfig();
	$monopoly_config->code = $argv[1];
	$monopoly_config->pwd = $argv[2];
	$monopoly_config->nais = $argv[3];
	
	$mm = new MonopolyManager($monopoly_config);
	
	print_r($mm->get_course_status($argv[4], $argv[5], $argv[6]));
	print_r($mm->get_course_info($argv[4]));
?>

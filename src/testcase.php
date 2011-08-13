<?php
	require_once("./Monopoly.php");
	
	$monopoly_config = new MonopolyConfig();
	$monopoly_config->code = $argv[1];
	$monopoly_config->pwd = $argv[2];
	$monopoly_config->nais = $argv[3];
	
	$mm = new MonopolyManager($monopoly_config);
	
	// test ici
?>

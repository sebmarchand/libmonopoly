<?php
	require_once("libmonopoly.php");
	

	log_event("notes", "Starting.");

	function load_grades_cache($filename) {
		$grades = array();
		
		if (file_exists($filename)) {
			$cache = file($filename, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
		
			foreach ($cache as $v) {
				$line = explode('|', $v); 
				$grades[$line[0]] = $line[1];
			}
		}
		return $grades;
	}

	function save_grades_cache($filename, $grades) {
		$out = "";
		foreach ($grades as $k => $v) {
			$out .= "$k|$v\n";
		}
		
		file_put_contents($filename, $out);
	}

	function diff_grades($new, $cache) {
		$diff = array();
		
		foreach ($new as $sigle => $note) {
			if (array_key_exists($sigle, $cache)) {
				if ($new[$sigle] != $cache[$sigle]) {
					$diff[$sigle] = $note;
					log_event("notes", "Nouvelles note ($sigle) : $note");
				}
			} else {
				$diff[$sigle] = "new";
				log_event("notes", "Nouveau cours : $sigle");
			}
		}
		
		return $diff; 
	}
	
	function notify_user($diff, $email) {
		if (count($diff) > 0) {
			$msg = "";
			foreach ($diff as $k => $v) {
				if ($v == "new") {
					$msg .= "Nouveau cours: $k\n";
				} else {
					$msg .= "Nouvelle note: $k = $v\n";
				}
			}
			mail($email, "Notes update", $msg, "From: Père Noël <perenoel@polenord.ca>\r\n");
			log_event("notes", "Mail sent to $email");
		}
	}
	
	$config = get_config();
	
	if (!$config) {
		exit();
	}

	$crap = get_crap($config['common']);

	if (!is_valid_crap($crap)) {
		exit();
	}
	
	$some_more_crap = get_some_more_crap($crap);
		
	$grades_new = get_grades($some_more_crap);
	$grades_cache = load_grades_cache($config['notes']['cache_filename']);
	$diff = diff_grades($grades_new, $grades_cache);
	
	notify_user($diff, $config['common']['email']);
	
	save_grades_cache($config['notes']['cache_filename'], $grades_new);
	log_event("notes", "Exiting with success.");	
?>

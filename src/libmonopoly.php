<?php
	define("CONFIG_FILENAME", "config.ini");

	require_once("libmonopoly-config.php");
	require_once("libmonopoly-log.php");

	function get_crap($config_common) {		
		$ch = curl_init("https://www4.polymtl.ca/servlet/ValidationServlet");
		curl_setopt($ch, CURLOPT_POSTFIELDS, "code={$config_common['code']}&nip={$config_common['pwd']}&naissance={$config_common['nais']}");
		curl_setopt($ch, CURLOPT_POST, TRUE);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		$res = curl_exec($ch);
		curl_close($ch);
		
		return $res;
	}

	/**
	* Checks if the given crap is valid, for example to check if the system is available or under pizza maintenance.
	*
	* @param crap_to_check Crap whose validity is questioned.
	* @return True if is valid crap.
	*/
	function is_valid_crap($crap_to_check) {
		return strstr($crap_to_check, '<font size="+2">Cliqu</font><font size="+2">ez sur la fonction d&eacute;sir&eacute;e</font>') !== FALSE;
	}
	
	function submit_changes(&$inputs) {
		$ch = curl_init("https://www4.polymtl.ca/servlet/ModifCoursServlet");
		curl_setopt($ch, CURLOPT_POSTFIELDS, build_post_string($inputs));
		curl_setopt($ch, CURLOPT_POST, TRUE);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($ch, CURLOPT_REFERER, "https://www4.polymtl.ca/servlet/ChoixCoursServlet"); 
		curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.9.2.3) Gecko/20100401 Firefox/3.6.3");
		curl_exec($ch);
		curl_close($ch);
	}
	
	function get_attrib_match($line, $param) {
		if (!preg_match("/$param\s*=\s*\"([^\"]*)\"/", $line, $m)) {
			if (!preg_match("/$param\s*=\s*\'([^\']*)\'/", $line, $m)) {
				if (!preg_match("/$param=([^ ]*)/", $line, $m)) {
					return false;
				}
			}
		}
		
		return $m[1];
	}
	
	function get_inputs_from_page(&$content) {
		$ret = array();
		
		preg_match_all('/<input[^>]+>/', $content, $m, PREG_SET_ORDER);
		foreach ($m as $k => $v) {
			$t = $v[0];
			$name = get_attrib_match($t, 'name');
			$value = get_attrib_match($t, 'value');
			$type = get_attrib_match($t, 'type');
			if ($name === false || $value === false || $type === false) {
				continue;
			}
			$ret[$name] = array(
				'type' => $type,
				'value' => $value
			);
		}
		
		return $ret;
	}
	
	function build_post_string(&$post) {
		$ret = "";
		
		foreach ($post as $k => $v) {
			$name = urlencode($k);
			$value = urlencode($v['value']);
			$ret .= "$name=$value&";
		}
		
		return substr($ret, 0, strlen($ret) - 1);
	}
	
	function get_more_crap(&$crap) {
		$post = get_inputs_from_page($crap);
		$ch = curl_init("https://www4.polymtl.ca/servlet/ChoixCoursServlet");
		curl_setopt($ch, CURLOPT_POSTFIELDS, build_post_string($post));
		curl_setopt($ch, CURLOPT_POST, TRUE);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		$res = curl_exec($ch);
		curl_close($ch);
		
		return $res;
	}
	
	function get_some_more_crap(&$crap) {
		$post = get_inputs_from_page($crap);
		$ch = curl_init("https://www4.polymtl.ca/servlet/PresentationResultatsTrimServlet");
		curl_setopt($ch, CURLOPT_POSTFIELDS, build_post_string($post));
		curl_setopt($ch, CURLOPT_POST, TRUE);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		$res = curl_exec($ch);
		curl_close($ch);
		
		return $res;
	}
	
	
	function get_course_bullshit(&$more_crap, $course, $type, $gr) {
		$g_gr = str_pad($gr, 2, "0", STR_PAD_LEFT);
		$g_type = strtoupper($type);
		if (!preg_match("/this\s*\[\s*\"$course$g_gr$g_type\"\s*\]\s*=\s*\"([^\"]+)\"\s*;/i", $more_crap, $m)) {
			return false;
		}
		$places = intval(substr($m[1], 0, 3));
		$h = substr($m[1], 3, 1);
		if (substr($m[1], 7, 1) != ' ') {
			$p = 6;
		} else {
			$p = 5;
		}
		$hor = explode(" ", trim(substr($m[1], $p)));
		return array(
			'places' => $places,
			'h' => $h,
			'hor' => $hor,
			'type' => strtoupper($type),
			'gr' => $gr
		);
	}
	
	function get_course_infos(&$more_crap, $course) {
		if (!preg_match("/this\s*\[\s*\"$course\"\s*\]\s*=\s*\"([^\"]+)\"\s*;/i", $more_crap, $m)) {
			return false;
		}
		$cr = intval(substr($m[1], 2, 2));
		$name_len = intval(substr($m[1], 7, 2));
		$name = substr($m[1], 9, $name_len);
		$type = substr($m[1], 4, 3);
		$is_final_proj = (substr($m[1], 1, 1) == 'O');
		$is_stage = (substr($m[1], 0, 1) == 'O');
		
		return array(
			'name' => $name,
			'cr' => $cr,
			'type' => $type,
			'is_final_proj' => $is_final_proj,
			'is_stage' => $is_stage
		);
	}
	
	function get_registered_courses_from_input($inputs) {
		$ret = array();
		
		for ($i = 1; $i <= 10; ++$i) {
			if (strlen($inputs["sigle$i"]['value']) > 0) {
				array_push($ret, $inputs["sigle$i"]['value']);
			}
		}
		
		return $ret;
	}
	
	function find_next_avai_input($inputs) {
		for ($i = 1; $i <= 10; ++$i) {
			if ($inputs["sigle$i"]['value'] == "") {
				return $i;
			}
		}
		
		return false;
	}
	
	function update_inputs(&$inputs, &$more_crap, &$added) {
		$nbr_cours = 0;
		$nbr_cr = 0;
		$nbr_cr_wo_pfe = 0;
		for ($i = 1; $i <= 10; ++$i) {
			$input = $inputs["sigle$i"];
			if (strlen($input['value']) > 0) {
				$course = $input['value'];
				$gr_th = $inputs["grtheo$i"]['value'];
				$gr_lb = $inputs["grlab$i"]['value'];
				$bullshit = get_course_bullshit($more_crap, $course, 't', $gr_th);
				$infos = get_course_infos($more_crap, $course);
				
				$inputs["titre$i"]['value'] = $infos['name'];
				$inputs["credits$i"]['value'] = $infos['cr'];
				$inputs["couIndPFE$i"]['value'] = ($infos['is_final_proj'] ? 'O' : 'N');
				$inputs["Isigle$i"]['value'] = $course;
				$inputs["Itype$i"]['value'] = $infos['type'];
				
				if ($added[$i]) {
					$inputs["Igrtheo$i"]['value'] = "";
					$inputs["Igrlab$i"]['value'] = "";
				} else {
					$inputs["Igrtheo$i"]['value'] = $inputs["grtheo$i"]['value'];
					$inputs["Igrlab$i"]['value'] = $inputs["grlab$i"]['value'];
				}
				
				++$nbr_cours;
				$nbr_cr += $infos['cr'];
				if (!$infos['is_final_proj']) {
					$nbr_cr_wo_pfe += $infos['cr'];
				}
			}
		}
		$inputs['bascule']['value'] = '';
		$inputs['errinit']['value'] = 'N';
		$inputs['nbr_cours']['value'] = $nbr_cours;
		$inputs['totalCredits']['value'] = $nbr_cr;
		$inputs['totalCreditsSansPFE']['value'] = $nbr_cr_wo_pfe;
	}
	
	function add_course_to_inputs(&$inputs, $more_crap, $course, $gr_th, $gr_lb, &$added) {
		if ($gr_th == 0) {
			$gr_th = "";
		} else {
			$gr_th = str_pad($gr_th, 2, "0", STR_PAD_LEFT);
		}
		if ($gr_lb == 0) {
			$gr_lb = "";
		} else {
			$gr_lb = str_pad($gr_lb, 2, "0", STR_PAD_LEFT);
		}
		$g_course = strtoupper($course);
		$d = find_next_avai_input($inputs);
		$inputs["sigle$d"]['value'] = $g_course;
		$inputs["grtheo$d"]['value'] = $gr_th;
		$inputs["grlab$d"]['value'] = $gr_lb;
		$added[$d] = true;
		
		update_inputs($inputs, $more_crap, $added);
	}
	
	function get_grades($more_crap) {
		preg_match_all('/<td width="125"><div align="right"><font size="1" face="Courier New", "Courier", "monospace">([^<]*)<\/font>/', $more_crap, $sigles);
		$sigles = $sigles[1];
		preg_match_all('/<td width="63"><div align="center"><font size="1" face="Courier New", "Courier", "monospace">([^<]*)<\/font>/', $more_crap, $notes);
		$notes = $notes[1];
		
		foreach($sigles as $k => $v) {
			$sigles[$k] = trim($sigles[$k]);
		}
		
		foreach($notes as $k => $v) {
			$notes[$k] = trim($notes[$k]);
		}

		if (count($sigles) > 0) {
			return array_combine($sigles, $notes);
		} else {
			return array();
		}
	}
?>

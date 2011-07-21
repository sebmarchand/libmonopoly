<?php
	define("CONFIG_FILENAME", "config.ini");

	/*require_once("libmonopoly-config.php");
	require_once("libmonopoly-log.php");*/

	class MonopolyConfig {
		public $code;
		public $pwd;
		public $nais;
	}
	
	class MonopolyCourseStatus {
		public $abbr;
		public $places;
		public $h;
		public $sched;
		public $type;
		public $gr;
	}
	
	class MonopolyCourseInfo {
		public $name;
		public $credits;
		public $is_final_project;
		public $is_internship;
		public $available_types;
	}
	
	class MonopolySched {	
		public $pernum;
		public $day_of_week;
		public $h;
		public $m;
	}

	class MonopolyManager {
		const VALIDATION_URL = 'https://www4.polymtl.ca/servlet/ValidationServlet';
		const MODIFCOURS_URL = 'https://www4.polymtl.ca/servlet/ModifCoursServlet';
		const CHOIXCOURS_URL = 'https://www4.polymtl.ca/servlet/ChoixCoursServlet';
		const PRESENTATION_URL = 'https://www4.polymtl.ca/servlet/PresentationResultatsTrimServlet';
		const FAKE_USER_AGENT = 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.9.2.3) Gecko/20100401 Firefox/3.6.3';
		const K_TYPE = 'type';
		const K_VALUE = 'value';
		const COURSE_LABO = 'L';
		const COURSE_THEO = 'T';
		
		private $_validation_raw_data = NULL;
		private $_modifcours_raw_data = NULL;
		private $_choixcours_raw_data = NULL;
		private $_presentation_raw_data = NULL;
		private $_config_vo = NULL;
		
		private function build_post_string($post, $look_at = NULL) {
			$ret = "";
		
			foreach ($post as $k => $v) {
				$name = urlencode($k);
				if (is_null($look_at)) {
					$value = urlencode($v);
				} else {
					$value = urlencode($v[$look_at]);
				}
				$ret .= "$name=$value&";
			}
		
			return substr($ret, 0, strlen($ret) - 1);
		}
		
		private function curl_post($url, $post_string, $curl_opts = array()) {
			$ch = curl_init($url);			
			curl_setopt($ch, CURLOPT_POSTFIELDS, $post_string);
			curl_setopt($ch, CURLOPT_POST, TRUE);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
			curl_setopt($ch, CURLOPT_USERAGENT, self::FAKE_USER_AGENT);
			foreach ($curl_opts as $k => $v) {
				curl_setopt($ch, $k, $v);
			}
			$res = curl_exec($ch);
			curl_close($ch);
			
			return $res;
		}		
		
		private function set_validation_raw_data() {
			$pf = array(
				'code' => $this->_config_vo->code,
				'nip' => $this->_config_vo->pwd,
				'naissance' => $this->_config_vo->nais
			);
			if (is_null($this->_validation_raw_data)) {
				$ps = $this->build_post_string($pf, NULL);
				$this->_validation_raw_data = $this->curl_post(self::VALIDATION_URL, $ps);
			}
		}
		
		private function set_raw_data_from_validation(&$to_set, $url, $force = false) {
			if (is_null($to_set) || $force) {
				$this->set_validation_raw_data();
				$pf = $this->get_inputs_from_raw_data($this->_validation_raw_data);
				$ps = $this->build_post_string($pf, self::K_VALUE);
				$to_set = $this->curl_post($url, $ps);
			}
		}
		
		private function set_choixcours_raw_data($force = false) {
			$this->set_raw_data_from_validation($this->_choixcours_raw_data, self::CHOIXCOURS_URL, $force);
		}
		
		private function set_presentation_raw_data($force = false) {
			$this->set_raw_data_from_validation($this->_presentation_raw_data, self::PRESENTATION_URL, $force);
		}
		
		private function get_inputs_from_raw_data(&$raw) {
			$ret = array();

			preg_match_all('/<input[^>]+>/', $raw, $m, PREG_SET_ORDER);
			foreach ($m as $k => $v) {
				$t = $v[0];
				$name = $this->get_attrib_match($t, 'name');
				$value = $this->get_attrib_match($t, 'value');
				$type = $this->get_attrib_match($t, 'type');
				if ($name === false || $value === false || $type === false) {
					continue;
				}
				$ret[$name] = array(
					self::K_TYPE => $type,
					self::K_VALUE => $value
				);
			}
		
			return $ret;
		}
		
		private function get_attrib_match($line, $param) {
			if (!preg_match("/$param\s*=\s*\"([^\"]*)\"/", $line, $m)) {
				if (!preg_match("/$param\s*=\s*\'([^\']*)\'/", $line, $m)) {
					if (!preg_match("/$param=([^ ]*)/", $line, $m)) {
						return false;
					}
				}
			}
		
			return $m[1];
		}
		
		public function __construct($config_vo, $set_validation = true) {
			$this->_config_vo = $config_vo;
			if ($set_validation) {
				$this->set_validation_raw_data();
			}
		}
		
		public function is_online() {
			$this->set_validation_raw_data();
			return strstr($this->_validation_raw_data, '<font size="+2">Cliqu</font><font size="+2">ez sur la fonction d&eacute;sir&eacute;e</font>') !== FALSE;
		}
		
		public function sched_from_pernum($pernum) {
			$pernum = trim($pernum);
			$p1 = $pernum[0];
			$p2 = $pernum[1];
			
			$sched_vo = new MonopolySched;
			$sched_vo->pernum = $pernum;
			$sched_vo->day_of_week = $p1 - 1;
			$sched_vo->h = 7 + $p2;
			if ($p2 < 5) {
				$sched_vo->m = 30;
			} else {
				$sched_vo->m = 45;
			}
			
			return $sched_vo;
		}
		
		public function get_course_status($abbr, $type, $gr) {
			$this->set_choixcours_raw_data();
			
			$r_gr = str_pad($gr, 2, "0", STR_PAD_LEFT);
			$r_abbr = strtoupper($abbr);
			$r_type = strtoupper($type);
			$to_match = sprintf("/this\s*\[\s*\"%s%s%s\"\s*\]\s*=\s*\"([^\"]+)\"\s*;/i", $r_abbr, $r_gr, $type);
			if (!preg_match($to_match, $this->_choixcours_raw_data, $m)) {
				return false;
			}
			$places = intval(substr($m[1], 0, 3));
			$h = (trim(substr($m[1], 3, 1)) != '');
			if (substr($m[1], 7, 1) != ' ') {
				$p = 6;
			} else {
				$p = 5;
			}
			$course_status_vo = new MonopolyCourseStatus();
			$sched = explode(" ", trim(substr($m[1], $p)));
			sort($sched);
			$course_status_vo->sched = array();
			foreach ($sched as $pernum) {
				array_push($course_status_vo->sched, $this->sched_from_pernum($pernum));
			}
			$course_status_vo->abbr = $r_abbr;
			$course_status_vo->type = $r_type;
			$course_status_vo->gr = $gr;
			$course_status_vo->places = $places;
			$course_status_vo->h = $h;
			
			return $course_status_vo;
		}
		
		public function get_course_info($abbr) {
			$this->set_choixcours_raw_data();
			
			$r_abbr = strtoupper($abbr);
			$to_match = sprintf("/this\s*\[\s*\"%s\"\s*\]\s*=\s*\"([^\"]+)\"\s*;/i", $r_abbr);
			if (!preg_match($to_match, $this->_choixcours_raw_data, $m)) {
				return false;
			}
			$cr = intval(substr($m[1], 2, 2));
			$name_len = intval(substr($m[1], 7, 2));
			$name = substr($m[1], 9, $name_len);
			$type = substr($m[1], 4, 3);
			$is_final_proj = (substr($m[1], 1, 1) == 'O');
			$is_int = (substr($m[1], 0, 1) == 'O');
		
			$course_info_vo = new MonopolyCourseInfo;
			$course_info_vo->name = $name;
			$course_info_vo->credits = $cr;
			$type = trim($type);
			$course_info_vo->available_types = array();
			for ($i = 0; $i < strlen($type); ++$i) {
				array_push($course_info_vo->available_types, $type[$i]);
			}
			$course_info_vo->is_final_project = $is_final_proj;
			$course_info_vo->is_internship = $is_int;
			
			return $course_info_vo;
		}
		
		public function get_validation_inputs() {
			$this->set_validation_raw_data();
			
			return $this->get_inputs_from_raw_data($this->_validation_raw_data);
		}
		
		public function test($params = NULL) {
			
		}
	}


/*
	function submit_changes(&$inputs) {
		$ch = curl_init("https://www4.polymtl.ca/servlet/ModifCoursServlet");
		curl_setopt($ch, CURLOPT_POSTFIELDS, build_post_string($inputs));
		curl_setopt($ch, CURLOPT_POST, TRUE);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($ch, CURLOPT_REFERER, "https://www4.polymtl.ca/servlet/ChoixCoursServlet"); 
		curl_setopt($ch, CURLOPT_USERAGENT, self::FAKE_USER_AGENT);
		curl_exec($ch);
		curl_close($ch);
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

		return array_combine($sigles, $notes);
	}*/
?>

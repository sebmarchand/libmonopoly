<?php
	/**
	 * Configuration de l'étudiant.
	 */
	class MonopolyConfig {
		public $code;	// Code étudiant
		public $pwd;	// Mot de passe
		public $nais;	// Date de naissance (format JJ/MM/AA)

		/**
		 * Constructeur - raccourci pour fixer les valeurs.
		 *
		 * @param code		Code étudiant
		 * @param pwd		Mot de passe
		 * @param nais		Date de naissance
		 */
		public function __construct($code = NULL, $pwd = NULL, $nais = NULL) {
			$this->code = $code;
			$this->pwd = $pwd;
			$this->nais = $nais;
		}
	}

	/**
	 * Statut d'un cours.
	 */
	class MonopolyCourseStatus {
		public $abbr;		// Sigle
		public $places;		// Nombre de places restant
		public $h;
		public $sched;		// Horaire
		public $type;		// Type (théorique ou laboratoire)
		public $gr;		// Numéro de groupe
	}

	/**
	 * Information sur un cours.
	 */
	class MonopolyCourseInfo {
		public $name;			// Nom tel que vu par le registrariat
		public $credits;		// Nombre de crédits
		public $is_final_project;	// Vrai si est un projet final
		public $is_internship;		// Vrai si est un stage
		public $available_types;	// Tableau de types de cours disponibles
	}

	/**
	 * Horaire d'un cours.
	 */
	class MonopolySched {
		public $pernum;		// Numéro/code de période
		public $day_of_week;	// Journée de la semaine (0 pour lundi)
		public $h;		// Heure (format 24h)
		public $m;		// Minute
	}

	/**
	 * Cours.
	 */
	class MonopolyCourse {
		public $abbr;		// Sigle
		public $gr_theo;	// Numéro de groupe théorique
		public $gr_lab;		// Numéro de groupe de laboratoire (NULL si aucun)
	}

	/**
	 * Gestionnaire Monopoly.
	 */
	class MonopolyManager {
		const VALIDATION_URL = 'https://www4.polymtl.ca/servlet/ValidationServlet';
		const MODIFCOURS_URL = 'https://www4.polymtl.ca/servlet/ModifCoursServlet';
		const CHOIXCOURS_URL = 'https://www4.polymtl.ca/servlet/ChoixCoursServlet';
		const PRESENTATION_URL = 'https://www4.polymtl.ca/servlet/PresentationResultatsTrimServlet';
		const FAKE_USER_AGENT = 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.9.2.3) Gecko/20100401 Firefox/3.6.3';
		const K_TYPE = 'type';
		const K_VALUE = 'value';
		const COURSE_LAB = 'L';		// Clef à utiliser pour un groupe de laboratoire
		const COURSE_THEO = 'T';	// Clef à utiliser pour un groupe théorique

		private $_validation_raw_data = NULL;		// Données brutes de la page de validation
		private $_choixcours_raw_data = NULL;		// Données brutes de la page de modification de choix de cours
		private $_presentation_raw_data = NULL;		// Données brutes de la page de présentation des résultats académiques finaux
		private $_registered_courses = NULL;		// Tableau des cours enregistrés
		private $_new_registered_courses = NULL;	// Tableau des nouveaux cours enregistrés
		private $_config_vo = NULL;

		/**
		 * Construit une chaine POST à partir d'un tableau.
		 *
		 * @param post		Tableau associatif (clef vers valeur)
		 * @param look_at	Si non NULL, clef à vérifier pour la valeur
		 * @return		Chaine POST bien construite
		 */
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

		/**
		 * Émet une requête POST avec cURL.
		 *
		 * @param url		URL
		 * @param post_string	Chaine POST bien construite
		 * @param curl_opts	Options cURL à ajouter
		 * @return		Résultat renvoyé par le serveur
		 */
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

		/**
		 * Met en cache les données brutes de validation.
		 */
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

		/**
		 * Met en cache les données brutes d'une page à partir des entrées de la page de validation.
		 *
		 * @param to_set	Chaine à fixer
		 * @param url		URL
		 * @param force		Forcer la mise en cache même si elle existe déjà
		 */
		private function set_raw_data_from_validation(&$to_set, $url, $force = false) {
			if (is_null($to_set) || $force) {
				$this->set_validation_raw_data();
				$pf = $this->get_inputs_from_raw_data($this->_validation_raw_data);
				$ps = $this->build_post_string($pf, self::K_VALUE);
				$to_set = $this->curl_post($url, $ps);
			}
		}

		/**
		 * Met en cache les données brutes de la page de mofication de choix de cours.
		 *
		 * @param force		Forcer la mise en cache
		 */
		private function set_choixcours_raw_data($force = false) {
			$this->set_raw_data_from_validation($this->_choixcours_raw_data, self::CHOIXCOURS_URL, $force);
		}

		/**
		 * Met en cache les données brutes de la page de présentation des résultats académiques finaux.
		 *
		 * @param force		Forcer la mise en cache
		 */
		private function set_presentation_raw_data($force = false) {
			$this->set_raw_data_from_validation($this->_presentation_raw_data, self::PRESENTATION_URL, $force);
		}

		/**
		 * Obtenir les entrées de formulaire d'une page.
		 *
		 * @param raw		Données brutes de la page
		 * @return		Entrées sous forme de tableau associatif
		 */
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

		/**
		 * Obtenir la valeur d'un attribut d'une balise XML.
		 *
		 * @param line		Ligne contenant l'attribut au complet
		 * @param param		Paramètre de l'attribut
		 * @return		Valeur de l'attribut ou false si non trouvée
		 */
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

		/**
		 * Construit le gestionnaire à partir d'une configuration d'étudiant.
		 *
		 * @param config_vo		Configuration de l'étudiant
		 * @param set_validation	Mettre immédiatement en cache les données brutes de la page de validation
		 */
		public function __construct($config_vo, $set_validation = true) {
			$this->_config_vo = $config_vo;
			$this->_new_registered_courses = array();
			if ($set_validation) {
				$this->set_validation_raw_data();
			}
		}

		/**
		 * Vérifie si le système distant est en ligne.
		 *
		 * @return	Vrai si est en ligne
		 */
		public function is_online() {
			$this->set_validation_raw_data();
			return strstr($this->_validation_raw_data, '<font size="+2">Cliqu</font><font size="+2">ez sur la fonction d&eacute;sir&eacute;e</font>') !== FALSE;
		}

		/**
		 * Obtenir un VO d'horaire à partir d'un code de période.
		 *
		 * @param pernum	Code de période
		 * @return		VO d'horaire
		 */
		private function sched_from_pernum($pernum) {
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

		/**
		 * Obtenir le statut d'un cours.
		 *
		 * @param abbr		Sigle
		 * @param type		Type du cours (théorique ou laboratoire)
		 * @param gr		Numéro de groupe
		 * @return		VO de statut ou NULL si aucun résultat
		 */
		public function get_course_status($abbr, $type, $gr) {
			$this->set_choixcours_raw_data();

			$r_gr = str_pad($gr, 2, "0", STR_PAD_LEFT);
			$r_abbr = strtoupper($abbr);
			$r_type = strtoupper($type);
			$to_match = sprintf("/this\s*\[\s*\"%s%s%s\"\s*\]\s*=\s*\"([^\"]+)\"\s*;/i", $r_abbr, $r_gr, $type);
			if (!preg_match($to_match, $this->_choixcours_raw_data, $m)) {
				return NULL;
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

		/**
		 * Obtenir les informations d'un cours.
		 *
		 * @param	Sigle
		 * @return	VO d'information ou NULL si aucun résultat
		 */
		public function get_course_info($abbr) {
			$this->set_choixcours_raw_data();

			$r_abbr = strtoupper($abbr);
			$to_match = sprintf("/this\s*\[\s*\"%s\"\s*\]\s*=\s*\"([^\"]+)\"\s*;/i", $r_abbr);
			if (!preg_match($to_match, $this->_choixcours_raw_data, $m)) {
				return NULL;
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

		/**
		 * Fixe le tableau interne des cours enregistrés.
		 *
		 * @param force		Forcer si déjà en cache
		 * @return		Vrai si succès
		 */
		private function set_registered_courses($force = false) {
			if (isset($this->_registered_courses) && !$force) {
				return false;
			}
			$ret = array();
			$this->set_choixcours_raw_data(true);
			$inputs = $this->get_inputs_from_raw_data($this->_choixcours_raw_data);

			for ($i = 1; $i <= 10; ++$i) {
				if (isset($inputs["sigle$i"]['value'])) {
					if (strlen($z = $inputs["sigle$i"]['value']) > 0) {
						$course = new MonopolyCourse;
						$course->abbr = strtoupper($z);
						$course->gr_theo = NULL;
						$course->gr_lab = NULL;
						if (isset($inputs["grtheo$i"]['value'])) {
							$course->gr_theo = intval($inputs["grtheo$i"]['value']);
						}
						if (isset($inputs["grlab$i"]['value'])) {
							$course->gr_lab = intval($inputs["grlab$i"]['value']);
						}
						array_push($ret, $course);
					}
				}
			}

			$this->_registered_courses = $ret;
			return true;
		}

		/**
		 * Trouve l'emplacement d'un cours dans le tableau interne des cours enregistrés.
		 *
		 * @param course	VO de cours
		 * @return		Clef du tableau ou false si aucun résultat
		 */
		private function find_registered_course($course) {
			$this->set_registered_courses();
			$course->abbr = strtoupper($course->abbr);

			$flag = -1;
			foreach ($this->_registered_courses as $k => $c) {
				$match = true;
				if ($c->abbr != $course->abbr) {
					$match = false;
				}
				if ($course->gr_theo != $c->gr_theo && !is_null($course->gr_theo)) {
					$match = false;
				}
				if ($course->gr_lab != $c->gr_lab && !is_null($course->gr_lab)) {
					$match = false;
				}
				if ($match) {
					$flag = $k;
					break;
				}
			}
			if ($flag >= 0) {
				return $flag;
			} else {
				return false;
			}
		}

		/**
		 * Trouve l'emplacement d'un cours dans le tableau interne des cours enregistrés.
		 *
		 * @param abbr		Sigle du cours
		 * @return		Clef du tableau ou false si aucun résultat
		 */
		private function find_registered_course_abbr($abbr) {
			$course = new MonopolyCourse;
			$course->abbr = strtoupper($abbr);
			$course->gr_theo = NULL;
			$course->gr_lab = NULL;

			return $this->find_registered_course($course);
		}

		/**
		 * Prépare les entrées de formulaire pour une soumission des cours enregistrés.
		 *
		 * @return	Tableau complet d'entrées de formulaire
		 */
		private function prepare_inputs_for_rc_submit() {
			$nbr_cours = 0;
			$nbr_cr = 0;
			$nbr_cr_wo_pfe = 0;

			$this->set_registered_courses();
			$inputs = $this->get_inputs_from_raw_data($this->_choixcours_raw_data);

			for ($i = 1; $i <= 10; ++$i) {
				$inputs["sigle$i"]['value'] = "";
				$inputs["grtheo$i"]['value'] = "";
				$inputs["grlab$i"]['value'] = "";
				$inputs["titre$i"]['value'] = "";
				$inputs["credits$i"]['value'] = "";
				$inputs["couIndPFE$i"]['value'] = "";
				$inputs["Isigle$i"]['value'] = "";
				$inputs["Itype$i"]['value'] = "";
				$inputs["Igrtheo$i"]['value'] = "";
				$inputs["Igrlab$i"]['value'] = "";
			}
			$i = 1;
			foreach ($this->_registered_courses as $course) {
				$course_info = $this->get_course_info($course->abbr);
				if (is_null($course_info)) {
					return false;
				}
				$inputs["sigle$i"]['value'] = $course->abbr;
				$inputs["grtheo$i"]['value'] = str_pad($course->gr_theo, 2, "0", STR_PAD_LEFT);
				$inputs["grlab$i"]['value'] = is_null($course->gr_lab) ? "" : str_pad($course->gr_lab, 2, "0", STR_PAD_LEFT);
				$inputs["titre$i"]['value'] = $course_info->name;
				$inputs["credits$i"]['value'] = $course_info->credits;
				$inputs["couIndPFE$i"]['value'] = ($course_info->is_final_project ? 'O' : 'N');
				$inputs["Isigle$i"]['value'] = $course->abbr;
				$inputs["Itype$i"]['value'] = str_pad(implode("", $course_info->available_types), 3, " ", STR_PAD_RIGHT);
				if (!in_array($course->abbr, $this->_new_registered_courses)) {
					$inputs["Igrtheo$i"]['value'] = $inputs["grtheo$i"]['value'];
					$inputs["Igrlab$i"]['value'] = $inputs["grlab$i"]['value'];
				}
				++$nbr_cours;
				$nbr_cr += $course_info->credits;
				if (!$course_info->is_final_project) {
					$nbr_cr_wo_pfe += $course_info->credits;
				}

				++$i;
			}
			$inputs['bascule']['value'] = '';
			$inputs['errinit']['value'] = 'N';
			$inputs['nbr_cours']['value'] = $nbr_cours;
			$inputs['totalCredits']['value'] = $nbr_cr;
			$inputs['totalCreditsSansPFE']['value'] = $nbr_cr_wo_pfe;

			return $inputs;
		}

		/**
		 * Obtenir une copie des cours enregistrés à date.
		 *
		 * @return	Copie des cours enregistrés
		 */
		public function get_registered_courses() {
			$this->set_registered_courses();

			foreach ($this->_registered_courses as $k => $v) {
				$ret[$k] = clone $v;
			}

			return $ret;
		}

		/**
		 * Retirer un cours enregistré de la cache interne.
		 *
		 * @param abbr		Sigle du cours
		 * @return		Vrai si retiré avec succès
		 */
		public function remove_registered_course($abbr) {
			$abbr = strtoupper($abbr);
			$key = $this->find_registered_course_abbr($abbr);
			if ($key === false) {
				return false;
			} else {
				unset($this->_registered_courses[$key]);
				if (in_array($abbr, $this->_new_registered_courses)) {
					$keys = array_keys($this->_new_registered_courses);
					unset($this->_new_registered_courses[$keys[0]]);
				}

				return true;
			}
		}

		/**
		 * Ajoute un cours aux cours enregistrés internes.
		 *
		 * @param course	VO de cours valide
		 * @return		Vrai si l'ajout a réussi
		 */
		public function add_to_registered_courses($course) {
			$key = $this->find_registered_course_abbr($course->abbr);
			$course->abbr = strtoupper($course->abbr);
			if ($key === false) {
				$status = $this->get_course_status($course->abbr, self::COURSE_THEO, $course->gr_theo);
				if (is_null($status)) {
					return false;
				}
				if ($status->places == 0) {
					return false;
				}
				if (!is_null($course->gr_lab)) {
					$status = $this->get_course_status($course->abbr, self::COURSE_LAB, $course->gr_lab);
					if (is_null($status)) {
						return false;
					}
					if ($status->places == 0) {
						return false;
					}
				}
				if (count($this->_registered_courses) < 10) {
					array_push($this->_registered_courses, $course);
					array_push($this->_new_registered_courses, $course->abbr);

					return true;
				}
			}

			return false;
		}

		/**
		 * Soumet les cours enregistrés pour une modification officielle des choix de cours;
		 * attention, l'étudiant a droit à un nombre maximal par session et cette méthode,
		 * appelée à répétition, fera descendre le décompte.
		 */
		public function submit_registered_courses() {
			$inputs = $this->prepare_inputs_for_rc_submit();
			$ps = $this->build_post_string($inputs, 'value');
			$this->curl_post(self::MODIFCOURS_URL, $ps);
		}

		/**
		 * Obtient un tableau associatif des résultats académiques finaux.
		 *
		 * @return	Résultats académiques finaux
		 */
		public function get_final_grades() {
			$this->set_presentation_raw_data();

			preg_match_all('/<td width="125"><div align="right"><font size="1" face="Courier New", "Courier", "monospace">([^<]*)<\/font>/', $this->_presentation_raw_data, $abbrs);
			preg_match_all('/<td width="63"><div align="center"><font size="1" face="Courier New", "Courier", "monospace">([^<]*)<\/font>/', $this->_presentation_raw_data, $grades);
			$abbrs = $abbrs[1];
			$grades = $grades[1];

			foreach($abbrs as $k => $v) {
				$abbrs[$k] = strtoupper(trim($abbrs[$k]));
			}
			foreach($grades as $k => $v) {
				$grades[$k] = trim($grades[$k]);
			}

			return array_combine($abbrs, $grades);
		}
	}
?>

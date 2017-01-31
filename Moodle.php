<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Moodle extends CI_Model {

	private $db;

	function __construct(){
		parent::__construct();
		$this->db = $this->load->database('moodle',TRUE);
	}

	function get_userid_from_session($session){
		$this->db->where('sid', $session);
		$query = $this->db->get('sessions');

		if($query->num_rows()>0){
			$data = $query->row_array();
			return $data['userid'];
		}else{
			return false; // No se encuentra SID
		}
	}

	function generate_username($name, $extra = NULL){
		//longitud maxima 16      15 +1 del extra    dgiron0
		//strtolower trim a firstname y lastname
		//si hay dos palabras tomaremos como nombre y apellido
		//si hay tres = nombre y 2 apellidos
		//si primer apellido longitud <=3 se junta con el segundo apellido
		//name str replace de unwanted array
		//en caso de que extra = NULL o 0 nada. Si extra > 0 se añade al nombre
		$username = "";

		$unwanted_array = array( 'Š'=>'S', 'š'=>'s', 'Ž'=>'Z', 'ž'=>'z', 'À'=>'A', 'Á'=>'A', 'Â'=>'A', 'Ã'=>'A', 'Ä'=>'A', 'Å'=>'A', 'Æ'=>'A', 'Ç'=>'C', 'È'=>'E', 'É'=>'E',
                            'Ê'=>'E', 'Ë'=>'E', 'Ì'=>'I', 'Í'=>'I', 'Î'=>'I', 'Ï'=>'I', 'Ñ'=>'N', 'Ò'=>'O', 'Ó'=>'O', 'Ô'=>'O', 'Õ'=>'O', 'Ö'=>'O', 'Ø'=>'O', 'Ù'=>'U',
                            'Ú'=>'U', 'Û'=>'U', 'Ü'=>'U', 'Ý'=>'Y', 'Þ'=>'B', 'ß'=>'Ss', 'à'=>'a', 'á'=>'a', 'â'=>'a', 'ã'=>'a', 'ä'=>'a', 'å'=>'a', 'æ'=>'a', 'ç'=>'c',
                            'è'=>'e', 'é'=>'e', 'ê'=>'e', 'ë'=>'e', 'ì'=>'i', 'í'=>'i', 'î'=>'i', 'ï'=>'i', 'ð'=>'o', 'ñ'=>'n', 'ò'=>'o', 'ó'=>'o', 'ô'=>'o', 'õ'=>'o',
                            'ö'=>'o', 'ø'=>'o', 'ù'=>'u', 'ú'=>'u', 'û'=>'u', 'ý'=>'y', 'þ'=>'b', 'ÿ'=>'y' );

		$name = strtolower(str_replace(array_keys($unwanted_array), array_values($unwanted_array), $name));

		$name_parts = explode(" ", $name);
		if(count($name_parts) == 2){
			$username = substr($name_parts[0], 0, 1) . $name_parts[1];

		}else if(count($name_parts) >= 3){

			if(strlen($name_parts[1]) <= 3){
				$username = substr($name_parts[0], 0, 1) . $name_parts[1] . $name_parts[2];

				if(!empty($name_parts[3]) && strlen($name_parts[2]) <= 3){
					$username = $username .$name_parts[3];
				}

			}else{
				$username = substr($name_parts[0], 0, 1) . $name_parts[1];
			}
		}


		if($extra > 0){ $username = $username . $extra; }

		if(strlen($username) <= 16){ return $username; }
		else{ return false; }
	}

	function user($data){
		$this->db->where('id', $data)
				->or_where('email', $data)
				->or_where('username', $data);

		$query = $this->db->get('user');
		if($query->num_rows() == 1){ return $query->row_array(); }
		return array();
	}

	function load_users(){
		$query = $this->db
				->select(['id','username', 'firstname', 'lastname', 'email', 'lastaccess', 'suspended'])
				->get('user');

		if($query->num_rows() > 0){
			return $query->result_array();
		}else{ return array(); }
	}

	function is_active($uid, $time = "-1 day"){
		if($this->user_exists($uid) === FALSE){ return FALSE; }
		$query = $this->db
			->where('id', $uid)
			// ->where('deleted', FALSE)
			// ->where('suspended', FALSE)
			->where('lastaccess >=', strtotime($time))
			->get('user');

		return ($query->num_rows() == 1);
	}

	function is_blocked($uid){
		$query = $this->db
			->where('id', $uid)
			->group_start()
				->where('deleted', TRUE)
				->or_where('suspended', TRUE)
			->group_end()
			->get('user');

		return ($query->num_rows() == 1);
	}

	function block_user($data){
		$this->db
			->set('suspended', true)
			->where('email', $data)
		->update('user');
	}

	function get_user_info($uid){
		$query = $this->db
			->select('id','firstname','lastname','email','lang')
			->where('id', $uid)
			->where('confirmed', true)
			->where('deleted', false)
			->where('suspended', false)
		->get('user');

		if($query->num_rows() == 1){
			$data = $query->row_array();
			$final['id'] = $data['id'];
			$final['name'] = $data['firstname'] .' ' .$data['lastname'];
			$final['email'] = $data['email'];
            $final['lang_iso'] = $data['lang'];
			return $final;
		}else{
			return false;
		}
	}

	function file($id){
		$query = $this->db
			->where('id', $id)
			->or_where('contenthash', $id)
		->get('files');

		if($query->num_rows() == 1){ return $query->row_array(); }
	}

	function download_user_files($user, $course = NULL, $full = FALSE){		//false= nombre->contenthash
		$query = $this->db
			->where('userid', $user)
			->where('filesize >', 0) // NO ARCHIVOS VACIOS
		->get('files');

		if($query->num_rows() > 0){
			if(
				($course === TRUE && $full === FALSE) ||
				($course === NULL && $full === TRUE)
			){
				return $query->result_array();
			}elseif(
				($course === NULL && $full === FALSE)
			){
				return array_column($query->result_array(), 'contenthash', 'filename');
			}

		}else{ return array(); }
	}

	/*function user_file_context($contextid){
		$query = $this->db
				->where('id', $contextid)
				->where('contextlevel', 70)
				->get('context');

		if($query->num_rows() > 0){
			return $query->result_array();
		}else{
			return array();
		}
	}

	function course_modules_instance($instanceid){
		$query= $this->db
					->where('id', $instanceid)   //se debe buscar el id o el instance?
					->get('course_modules');

		if($query->num_rows() > 0){
			return $query->result_array();
		}else{
			return array();
		}
	}*/

	function get_user_avatar($uid){
		$this->db->select('picture');
		$this->db->where('id', $uid);

		$query = $this->db->get('user');

		if($query->num_rows() == 1){
			$pid = $query->row_array();
			$pid = $pid['picture'];
			if($pid > 1){
				$this->db->where('id', $pid)
						->where('component', 'user')
						->where('filearea', 'icon');

				$query = $this->db->get('files');

				if($query->num_rows() == 1){
					$data = $query->row_array();
					$file = $this->config->item('moodle_dir_data') .'filedir/' .substr($data['contenthash'],0,2) .'/' .substr($data['contenthash'],2,2) .'/' .$data['contenthash'];
					if(file_exists($file)){
						return file_get_contents($file);
					}
				}
			}
		}
		return false;
	}

	function get_user_full_name($uid){
		$this->db->select('CONCAT(firstname," ",lastname) AS name', false)
				 ->where('id', $uid)
				 ->where('confirmed', true)
				 ->where('deleted', false)
				 ->where('suspended', false);

		$query = $this->db->get('user');

		if($query->num_rows() == 1){
			$data = $query->row_array();
			return $data['name'];
		}else{ return false; }
	}

	function get_user_courses($uid){
		$this->db->select(array('user_enrolments.id','user_enrolments.enrolid','enrol.courseid'));
		$this->db->from('user_enrolments');
		$this->db->join('enrol', 'user_enrolments.enrolid = enrol.id');
		$this->db->where('user_enrolments.userid', $uid);

		$query = $this->db->get();
		if($query->num_rows()>0){
			foreach($query->result_array() as $enrol){ $final[$enrol['id']] = $enrol['courseid']; }
			return $final;
		}else{
			return false;
		}
	}

	function get_user_roles($array, $short = false){
		// array contiene $array[enrolid] = course.id;
		$this->db->select(array('role_assignments.id','role.id AS roleid','role.shortname'));
		$this->db->from('role');
		$this->db->join('role_assignments', 'role_assignments.roleid = role.id');
		// $this->db->where_in('role_assignments.id', @array_keys($array));

		$query = $this->db->get();
		if($query->num_rows()>0){
			foreach($query->result_array() as $rol){
				if($short){$final[$array[$rol['id']]] = $rol['shortname'];}
				else{$final[$array[$rol['id']]] = $rol['roleid'];}
			}
			return $final;
		}else{
			return false;
		}
	}

	function get_user_role($uid, $cid){
		$this->db->select(array('contextid'));
		$this->db->where('userid', $uid);

		$query = $this->db->get('role_assignments');
		if($query->num_rows()>0){
			foreach($query->result_array() as $rol){ $contexts[] = $rol['contextid']; }

			$this->db->select(array('id'));
			$this->db->where('contextlevel', 50); // Course ID
			$this->db->where_in('id', $contexts);
			$this->db->where('instanceid', $cid); // Find course directly.

			$query = $this->db->get('context');
			if($query->num_rows() == 1){
				$ctx = $query->row_array();
				$ctx = $ctx['id'];

				$this->db->select('roleid');
				$this->db->where('contextid', $ctx);
				$this->db->where('userid', $uid);
				$query = $this->db->get('role_assignments');

				if($query->num_rows()>0){
					$data = $query->row_array();
					return $data['roleid'];
				}
			}
		}
		return false;
	}

	function is_enrolled($uid, $course){
		$rol = $this->get_user_role($uid, $course);
		return (!empty($rol));
		// return (in_array($rol, $this->config->item('curso_tareas_acceso')));
	}

	// Buscar el/los IDs de enrol. Nada que ver con el ROL de usuario!
	function enrols($cid, $type = NULL){
		$this->db
			->select('id')
			->where('courseid', $cid);
		if(!empty($type)){
			if(is_array($type)){ $this->db->where_in('enrol', $type); }
			else{ $this->db->where('enrol', $type); }
		}

		$query = $this->db->get('enrol');

		if($query->num_rows()>0){
			// if($query->num_rows() == 1){ return $this-> }

			// ARREGLAR ESTO, si coinciden 2+ TYPE se queda solo 1!!!
			foreach($query->result_array() as $enrol){ $final[$cid] = $enrol['id']; }

			return $final;
		}

		return array();
	}

	function user_enrolments($uid, $cid){
		// Calculamos CourseID en base al EnrolID (con un SELECT tipo JOIN)
		/* SELECT mdl_user_enrolments.id, mdl_user_enrolments.status, mdl_user_enrolments.enrolid, mdl_user_enrolments.userid, mdl_user_enrolments.timestart, mdl_user_enrolments.timeend, mdl_user_enrolments.timecreated, mdl_enrol.courseid
		FROM mdl_user_enrolments
		JOIN mdl_enrol ON mdl_enrol.id = mdl_user_enrolments.enrolid
		WHERE mdl_enrol.courseid = 2 */

		$query = $this->db
			->select(['user_enrolments.id', 'user_enrolments.status', 'user_enrolments.enrolid', 'user_enrolments.userid', 'user_enrolments.timestart', 'user_enrolments.timeend', 'user_enrolments.timecreated', 'enrol.courseid'])
			->from('user_enrolments')
			->join('enrol', 'user_enrolments.enrolid = enrol.id')
			->where('user_enrolments.userid', $uid)
			->where('enrol.courseid', $cid)
			->get();

		if($query->num_rows()>0){
			return $query->row_array();
		}
		else{ return array(); }
	}

	function last_user_enrolments($cid, $date = NULL){
		if(empty($date) or $date > strtotime("now")){ $date = "-1 day"; }
		$date = strtotime($date);

		// SELECT mdl_user_enrolments.*, mdl_enrol.courseid
		// FROM `mdl_user_enrolments`
		// JOIN mdl_enrol ON mdl_enrol.id = mdl_user_enrolments.enrolid
		// WHERE mdl_enrol.courseid IN (48,51,52) AND mdl_user_enrolments.timecreated > 1452450989

		$this->db
			->select(['user_enrolments.*', 'enrol.courseid'])
			->from('user_enrolments')
			->join('enrol', 'enrol.id = user_enrolments.enrolid')
			->where('user_enrolments.timecreated >', $date);
		if($cid !== TRUE){ // (!is_array($cid) && strtoupper($cid) != "ANY") --> ANY Peta, no se puede convertir array a strtoupper :(
			$this->db->where_in('enrol.courseid', $cid);
		}

		$query = $this->db->get();

		if($query->num_rows()>0){ return $query->result_array(); }
		return array();
	}

	function users_first_access($time = NULL){
		if($time !== NULL){ $time = strtotime($time, 0); }
		else{ $time = 0; }

		$this->db
			->where('(firstaccess - (firstaccess % 86400)) +'. $time .'=', strtotime('today')+7200) // GMT+2
			->get_compiled_select('user', FALSE);
		$query = $this->db->get();

		if($query->num_rows() > 0){
			return $query->result_array();
		}else{ return array(); }
	}

	function context($level = NULL, $instance = NULL, $onlyid = false){
		if(!empty($level)){ $this->db->where('contextlevel', $level); }
		if(!empty($instance)){ $this->db->where('instanceid', $instance); }

		$query = $this->db->get('context');
		if($query->num_rows() == 1){ if($onlyid){ return $query->row()->id; } else { return $query->row_array(); } }
		elseif($query->num_rows()>1){ if($onlyid){ foreach($query->result_array() as $c){ $ctx[] = $c['id']; } return $ctx; } else { return $query->result_array(); } }
		else{ return false; }
	}

	function role($shortname, $onlyid = false){
		// $this->db->where('archetype', $archetype);
		// if(!empty($shortname)){ $this->db->where('shortname', $shortname); }
		$this->db->where('shortname', $shortname)->order_by('sortorder', 'ASC');

		$query = $this->db->get('role');
		if($query->num_rows() == 1){
			if($onlyid){ return $query->row()->id; }
			return $query->row_array();
		}elseif($query->num_rows() > 1){
			if($onlyid){ return array_column($query->result_array(), 'id'); }
			return $query->result_array();
		}
		return false;
	}

	function user_enroll($uid, $course, $method = 'manual', $roltype = 'student', $maxtime = 0){
		$query = $this->db->select('id')->where('courseid', $course)->where('enrol', $method)->get('enrol');
		if($query->num_rows() == 1){ $enrolid = $query->row()->id; } // 48 -> 194
		else{ return false; }

		if($maxtime>0){ $maxtime = time() + $maxtime; }

		$enrolment = [
			'status' => 0, // Desconocido
			'enrolid' => $enrolid,
			'userid' => $uid,
			'timestart' => strtotime(date("Y-m-d")),
			'timeend' => $maxtime,
			'modifierid' => 2, // Desconocido
			'timecreated' => time(),
			'timemodified' => time(),
		];

		$role_assign = [
			'roleid' => $this->role($roltype, true),
			'contextid' => $this->context(50, $course, true),
			'userid' => $uid,
			'timemodified' => time(),
			'modifierid' => 2, // Desconocido
			'component' => '',
			'itemid' => 0,
			'sortorder' => 0,
		];

		$this->db->insert('user_enrolments', $enrolment);
		$this->db->insert('role_assignments', $role_assign);
	}

	function course($id, $available = true){
		$this->db->where('id', $id);
		if($available){ $this->db->where('visible', TRUE); }

		$query = $this->db->get('course');
		if($query->num_rows() == 1){ return $query->row_array(); }
		else{ return false; }
	}

	function course_modules($data, $search = 'course', $manual_order = NULL){
		if($manual_order === TRUE && $search == 'course'){
			$manual_order = array('id');
			// var_dump(array_values($this->course_modules_order($data)));
			foreach(array_values($this->course_modules_order($data)) as $section){
				if(!empty($section)){
					if(is_numeric($section)){ $manual_order[] = (int) $section; }
					else{ array_push($manual_order, $section); }
				}
			}
			// array_push($manual_order, implode(",", array_values($this->course_modules_order($data))));
		}

		if(!in_array($search, ['course', 'id', 'instance'])){ return array(); }
		$this->db->where_in($search, $data);
		if(!empty($manual_order) && count($manual_order) > 1){
			$this->db->order_by("FIELD (" .implode(",", $manual_order) .")", "", FALSE);
		}

		// var_dump($this->db);// echo $this->db->last_query();
		$query = $this->db->get('course_modules');

		if($query->num_rows() > 0){
			return $this->__parse_array_index_id($query->result_array(), 'id');
		}else{
			return array();
		}
	}

	function assign_module($courseid, $instance, $module = "assign", $returnall = FALSE){
		if(!is_numeric($module) && !empty($module)){
			$module = $this->module($module);
			if(is_array($module)){ $module = key($module); }
		}

		$this->db
			->where('course', $courseid)
			->where('instance', $instance);

		if(!empty($module)){ $this->db->where('module', $module); }

		$query = $this->db->get('course_modules');
		if($query->num_rows()>0){
			if($returnall){
				return $query->row_array();
			}else{
				return $query->row()->id;
			}
		}
	}

	function course_content($cid, $modules = NULL, $order = NULL){
		// GET a course_modules todo
		// si está order y NO NULL, pues ORDER BY FIELD(id, .......)
		$this->db->where('course', $cid);

		if(!empty($modules)){
			$this->db->where_in('module', $modules);
		}
		if(!empty($order)){
			$this->db->order_by("FIELD (id, " . implode(",", $order). ")", "", FALSE);
		}
		$query = $this->db->get("course_modules");

		if($query->num_rows()>0){
			return $query->result_array();
		}else{
			return array();
		}
	}

	function course_sections($cid){
		$this->db->where('course', $cid);
		$this->db->order_by('section', 'ASC');
		$query = $this->db->get('course_sections');

		if($query->num_rows() > 0){
			return $this->__parse_array_index_id($query->result_array(), 'id');
		}else{
			return array();
		}
	}

	function course_modules_order($cid){
		$sections = $this->course_sections($cid);
		$modules = implode(",", array_column($sections, 'sequence'));
		$modules = explode(",", $modules);
		$final = array();
		foreach($modules as $m){ if(!empty($m)){ $final[] = $m; } }
		return $final;
	}

	function assign($id = NULL, $course = NULL, $full = FALSE){
		if(empty($id) && empty($course)){ return array(); }
		if(!empty($id)){ $this->db->where_in('id', $id); }
		if(!empty($course)){ $this->db->where('course', $course); }

		if(is_array($id)){
			$this->db->order_by('FIELD (id, ' .implode(",", array_values($id)) .")", "", FALSE);
		}

		$query = $this->db->get('assign');
		if($query->num_rows() == 1){
			if(!$full){ return $query->row()->id; }
			else{ return $query->row_array(); }
		}elseif($query->num_rows()>0){
			if(!$full){ return $this->__parse_array_index_id($query->result_array(), 'id'); }
			else{ return $query->result_array(); }
		}else{
			return array();
		}
	}

	function assign_by_name($search, $course = NULL, $like = FALSE, $returnall = FALSE){
		if($like){ $this->db->like('name', $search); }
		else{ $this->db->where('name', $search); }

		if($course!==NULL){
			$this->db->where_in('course', $course);
		}

		$query= $this->db->get('assign');

		if($query->num_rows()>0){
			if($returnall){ return $query->result_array(); }
			else{ return array_column($query->result_array(), 'id'); }
		}
	}

	function assign_grades($assigns, $user = NULL, $time = "-5 days"){
		// assigns puede ser uno o varios (array)
		// Usuario igual.
		// Si hay usuario, busca sólo para esos usuarios, si no, busca a todos.
		// Devolver array de $datos[352] => [23 => 75, ......] // [ìd user] => [àctividad => nota]

		if(!is_array($assigns)){ $assigns = array($assigns); }

		$this->db->where_in('assignment', $assigns);

		if(!is_array($user) && $user !== NULL){ $user = array($user); }

		if($user !== NULL){ $this->db->where_in('userid', $user); }

		$this->db->where('timemodified >=', strtotime($time));
		$this->db->where('grade >', 0);
		// echo $this->db->get_compiled_select('assign_grades', FALSE);
		$query= $this->db->get('assign_grades');

		$data = array();

		if($query->num_rows()>0){
			foreach($query->result_array() as $res){
				$data[$res['userid']][] = [$res['assignment'] => $res['grade']];
			}
		}

		return $data;
	}

	function session($sid, $uid = NULL){
		$this->db->where('sid', $sid);
		if(!empty($uid)){ $this->db->where('userid', $uid); }

		$query = $this->db->get('sessions');
		if($query->num_rows() == 1){
			return $query->row_array();
		}else{
			return FALSE;
		}
	}

	function session_id($uid, $limit = 1){
		$query = $this->db
			->where('userid', $uid)
			->order_by('timemodified', 'DESC')
			->limit($limit)
			->get('sessions');

		if($query->num_rows() == $limit){
			if($query->num_rows() == 1){ return $query->row()->sid; }

			foreach($query->result_array() as $sess){ $final[] = $sess['sid']; }
			return $final;
		}else{ return NULL; }
	}

	function session_by_ip($ip = NULL, $limit = 1, $fullinfo = FALSE){
		if(empty($ip)){ $ip = $_SERVER['REMOTE_ADDR']; }
		$query = $this->db
			->where('lastip', $ip)
			->limit($limit)
			->get('sessions');

		if($query->num_rows()>0){
			if(!$fullinfo){ return array_column($query->result_array(), 'sid'); }
			else{
				return $query->result_array();
			}
		}
	}

	function user_assign_grade($uid, $aid, $fulldata = FALSE){
		$this->db
			->where('userid', $uid)
			->where_in('assignment', $aid);

		if(is_array($aid)){
			$this->db->order_by("FIELD (assignment, " .implode(",", $aid) .")", "", FALSE);
		}

		$query = $this->db->get('assign_grades'); // No hay AID?
		//return $this->db->last_query();
		//die();
		if($query->num_rows()>0){
			if($fulldata){ return $this->__parse_array_index_id($query->result_array(), 'id'); }
			else{ return array_column($query->result_array(), 'grade', 'assignment'); }
		}else{
			return array();
		}
	}

	function user_last_access($uid, $cid = NULL){
		// Si es NULL, cojer last access de la cuenta.
		// Si no, buscar en la tabla mdl_user_lastaccess
		if($cid===NULL){
			$query = $this->db
				->select('lastaccess AS timeaccess')
				->where('id', $uid)
				->get('user');
		}else{
			$query = $this->db
				->select('timeaccess')
				->where('userid', $uid)
				->where('courseid', $cid)
				->get('user_lastaccess');
		}

		if($query->num_rows()>0){
			return date("Y-m-d H:i:s", $query->row()->timeaccess);
		}

		return FALSE;
	}

	function users_last_access($lastaccess = "-20 days"){
		$date = date("Y-m-d H:i:s", strtotime("+1 day", strtotime($lastaccess)));

		$query = $this->db
				->where('lastaccess <', strtotime($lastaccess))  //menor de 20 dias
				->where('lastaccess >', strtotime("-2 month", strtotime($lastaccess))) //mayor de 20 dias + 2 meses
				->where('suspended', FALSE)  //cuando no esté bloqueado
				->get('user');

		//echo $this->db->last_query();

		if($query->num_rows()>0){
			return $query->result_array();
		}else return array();

	}

	function check_user_lastlogin($uid){
		if(!empty($uid)){
			$user = $this->user($uid);
			$final = array();

			if(!empty($user)){
				$today = strtotime("now")+7200; // GMT +2
				$todayStart = $today - ($today%86400);
				$todayFinish = $todayStart + 86399;

				//$final["2W"] = ($todayStart-1209600) ."->". ($todayFinish - 1209600); // Rango del dia
				if($user['lastaccess'] >= ($todayStart - 1209600) && $user['lastaccess'] <= ($todayFinish-1209600)){   // Esta en el rango del dia de hace 2 semanas
					$final["2W"] = $user;
				}elseif($user['lastaccess'] >= ($todayStart - 2419200) &&   $user['lastaccess'] <= ($todayFinish-2419200)){  // Esta en el rango del dia de hace 4 semanas
					$final["4W"] = $user;
				}else{ $final = FALSE; }
			}
		}else{ $final = FALSE; }
		return $final;
	}

	function user_finished($uid, $cid = NULL){
		//Busca la ultima actividad del curso, si no hay curso busca los cursos que tenga y hazlo por cada uno
		//mirar si la actividad no es NULL y >0
		if(empty($cid)){
			$userCourses = $this->get_user_courses($uid);
		}elseif(!empty($cid) && !is_array($cid)){
			$cid = array($cid);
		}

		foreach($cid as $c){
			$c = $this->course($c);
			//$this->user_enrolments($uid, $c);
		}
	}

	/*function user_course_finished($uid, $cid){
		$this->db
			->where('userid', $uid)
			->where()
			->get('user_enrolments');

	}*/

	function grades_from_course($uid, $cid){
		$mod = $this->moodle->course_modules($cid, 'course', TRUE);
		$assigns = $this->moodle->assign(array_column($mod, 'instance'), $cid);
		if(empty($assigns)){ return FALSE; }
		$grades = $this->moodle->user_assign_grade($uid, array_keys($assigns));

		// $gradesResult = array();
		// $gradesAverage= array();
		// $result= array();
		$result = array();
		$counter = 0;

		foreach($assigns as $actividad){
			$result[$actividad['id']] = (empty($grades[$actividad['id']]) ? NULL : $grades[$actividad['id']]);
		}

		return $result;
	}

	function grades_not_checked(){
		$query = $this->db
			->where('timecreated IS NOT NULL')
			->group_start()
				->where('rawgrade IS NULL')
				->or_where('finalgrade IS NULL')
			->group_end()
			->get('grade_grades');

		if($query->num_rows()>0){
			// Devolver ID de actividad del curso correspondiente.
			return $query->result_array();
			foreach($query->result_array() as $act){

			}
		}

		return array();
	}

	function grade_item($items = NULL, $courseid = NULL, $modules = NULL){
		if(empty($items) && empty($courseid)){ return FALSE; }
		if(!empty($items)){
			$this->db->where_in('id', $items);
		}

		if(!empty($courseid)){
			$this->db->where('courseid', $courseid);
		}

		if(!empty($modules)){
			$this->db->where_in('itemmodule', $modules);
		}

		$query = $this->db->get('grade_items');

		if($query->num_rows()>0){
			return $query->result_array();
		}

		return array();
	}

	function is_siteadmin($userid){
		// Buscar en la config siteadmins / ID 19 de MDL_Config
		// $query = $this->db->where('name', 'siteadmins')->get('config');
		$admins = $this->config('siteadmins');
		$admins = explode(",", $admins);

		return (in_array($userid, $admins));
	}

	function config($search, $data = NULL){
		if(is_numeric($search)){ $this->db->where('id', $search); }
		else{ $this->db->where('name', $search); }

		if($data === NULL){
			$query = $this->db->get('config');
			if($query->num_rows() == 1){ return $query->row()->value; }
			return NULL;
		}
	}

	function module($mid, $newinfo = FALSE){

		if(!is_array($mid)){
			$mid = array($mid);
		}

		$this->db->where_in('id', $mid);
		$this->db->or_where_in('name', $mid);

		$query= $this->db->get('modules');
		if($query->num_rows() == 1){ return $query->row_array(); }
		elseif($query->num_rows() > 1){
			if(!$newinfo){ return $query->result_array(); }
			else{ return array_column($query->result_array(), 'id', 'name'); }
		}

		return array();
	}

	function course_activities($cid){
		$this->db
			->select(['id', 'name'])
			->where('course', $cid)
			->order_by ('id','ASC');
		$query = $this->db->get('assign');

		if($query->num_rows()>0){
			foreach($query->result_array() as $a){
				$actividades[$a['id']] = $a;
				$actividades[$a['id']]['instance'] = $this->context(30, $a['id'], TRUE); // ID de instancia de assign
			}

			$this->db->select(['id','sequence']);
			$this->db->where('course', $cid);
			$this->db->order_by('id','ASC');
			$query = $this->db->get('course_sections');

			$sequence = array();

			if ($query->num_rows()>0){
				foreach ($query->result_array() as $a) {
					$sequence[] = $a['sequence'];
				}

				// Juntar y ordenar los sequence
				$sequence = implode(",", array_values($sequence));
				$sequence = explode(",", $sequence);

				var_dump($actividades);

				//comparar la sequence con la instance id
				//doble foreach
				foreach ($sequence as $seq){
					echo $seq .' -> ';
					foreach ($actividades as $a) {
						echo $a['instance'] .' ';
						if ($seq == $a['instance']){
							$final[] = $a;
						}
					}
					echo '<br />' ."\n";
				}
				return $final; // Devuelvo array ordenado de las actividades.
			}
		}
		return [];
	}

	function course_grading($courseid, $user = NULL, $onlyfinalmark = TRUE){
		// GET ID de todos los MODULES que son assign, assignment, quiz (aunque aun no lo vamos a usar, pero tener pendiente) (1, 21 o 14)
		// GET course_sections, ORDENAR por SECTION y cojer todos los SEQUENCE. CONCATENAR CON implode (,..., ...)  -> hecho : course_sections()
		// GET course_modules, ORDENAR POR FIELD (orden de los IDS que hemos cogido antes) WHERE MODULES sean los de antes. OKK
		// En función del tipo de módulo que sea, tendremos que ir a la tabla correspondiente (mdl_assign, mdl_quiz....) para sacar info de la actividad (ID real de assign, nombre, lo que sea.)
		// GET peso / aggregation por defecto (y TIPO CALCULO CURSO)
		// GET notas del alumno en su assign y guardar temp.

		// cuando esté todo listo, multiplicar / sumar / loquesea la nota de las actividades por el PESO y hacer media ponderada (según config curso) :)

		// PD: Luego para chinchar puedo pedir devolver el array con la nota, peso y calculo total, aparte de devolver solo el total

		$mod = $this->module(['assignment', 'assign', 'quiz'], TRUE);   // $mod= id, nombre
		$order = $this->course_modules_order($courseid);
		$activities = $this->course_content($courseid, array_values($mod), $order);

		// JOIN assign + course_modules
		foreach($activities as $k => $a){
			if($a['module'] == $mod['assign']){
				$activities[$k] = array_merge($activities[$k], $this->assign($a['instance'], NULL, TRUE));
			}
		}

		// FALTA: calcular peso y demás
		$pesosCurso = $this->course_aggregation($courseid);
		$grades = $this->user_assign_grade($user, array_column($activities, 'id'), TRUE);

		// $act_final = array_values($grades) + array_values($pesosCurso);

		for($i = 0; $i < count($activities); $i++){
			$act_final[] = array_merge(array_values($grades)[$i], array_values($pesosCurso)[$i]);
		}

		$nota = 0;

		// Proyecto final NO INCLUIDO
		for($i = 0; $i < count($act_final) - 1; $i++){
			$nota += $act_final[$i]['grade'];
		}

		$currentGrade = $nota / (count($act_final)-1);

		foreach($act_final as $act){
			$allFinalData[]=[
				'name' => $act['name'],
				'weight' => $act['weight'],
				'grade' => $act['grade']
			];
		}

		if($onlyfinalmark){
			return $currentGrade;
		}else{
			return $allFinalData;
		}
	}


	function course_aggregation($cid){
		$this->db->select(['id', 'courseid', 'itemname', 'aggregationcoef']);
		$this->db->where('courseid', $cid)
				->where_not_in('itemmodule', 'course');   //coger los assign
		$query = $this->db->get('grade_items');

		if ($query->num_rows() > 0){
			foreach ($query->result_array() as $grade) {
				$peso[] = [
					'id' => $grade['id'],
					"name" => $grade['itemname'],
					"weight" => $grade['aggregationcoef']
				];
			}
			return $peso;
		}else return array();
	}

	/*function user_assign_grade($uid, $assignid, $alldata = false){
		if(!$alldata){ $this->db->select('grade'); }
		$this->db->where('userid', $uid)->where_in('assignment', $assignid); // uno o varios en array
		$query = $this->db->get('assign_grades');

		if($query->num_rows()>0){
			if($alldata){ return $query->result_array(); }
			return array_column($query->result_array(), 'grade');
		}
	}*/

	/* function user_activities($uid, $courseid){

		// Comprobar si existe el usuario

		if ($this->user_exists($uid) && $this->course($courseid) !== FALSE){
			$activities = $this->course_activities($courseid);



		/*	$this->db->select(['id', 'courseid', 'itemname', 'aggregationcoef']);
			$this->db->where ('courseid', $courseid);
			$query = $this->db->get('grade_items')

			if ($query -> num_rows()>0){
				foreach ($query->result_array() as $grade) {

					$course[$grade['id']] = $grade;
				}

				$this->db->select('id', 'courseid', 'aggregation');
				$this->db->where ('courseid', $courseid)
				$query = $this->db->get('grade_categories');

				if ($query->num_rows()>0){
					foreach ($query->result_array() as $grade ) {

						$course[$grade['id']] ->$grade['courseid'] ->$grade['aggregation'];

					}
				}

			}*/







		/* }else {
			return false;

		}



		// Comprobar si existe el curso

		// Comprobar si el usuario está matriculado en el curso

		// $this->user_exists()

		// Cargar curso



		// Cargar actividades del curso ID y nombre
		// Cargar orden de actividades
		// Añadir nota





		// Ejemplo final
		/*$array = [
		array('id' => 1489, 'mark' => 60, 'lastupdated' => 121651651),
		array('id' => 1495, 'mark' => 70, 'lastupdated' => 121651651),
		array('id' => 1489, 'mark' => NULL), // Porque no la ha hecho
		] */
	// }

	function user_exists($data, $return = false){
		if(is_numeric($data)){ $this->db->where('id', $data); }
		elseif(filter_var($data, FILTER_VALIDATE_EMAIL)){ $this->db->where('email', $data); }
		else{ $this->db->where('username', $data); }

		$query = $this->db->get('user');
		if($query->num_rows() == 1){
			if($return){ return $query->row()->id; }
			return true;
		}
		return false;
	}

	function user_register($user, $password, $email, $name, $lastname, $data = array(), $force_changepass = true){
		// Comprobar si el usuario existe antes!
		$default = [
			'auth' => 'manual',
			'confirmed' => TRUE,
			'policyagreed' => FALSE,
			'deleted' => FALSE,
			'suspended' => FALSE,
			'mnethostid' => 1,
			'username' => $user,
			'password' => $this->hash_password($password),
			'idnumber' => '',
			'firstname' => $name,
			'firstnamephonetic' => NULL,
			'lastname' => $lastname,
			'lastnamephonetic' => NULL,
			'middlename' => NULL,
			'alternatename' => NULL,
			'email' => $email,
			'emailstop' => 0,
			'icq' => '',
			'skype' => '',
			'yahoo' => '',
			'aim' => '',
			'msn' => '',
			'phone1' => '',
			'phone2' => '',
			'institution' => '',
			'department' => '',
			'address' => '',
			'city' => '',
			'country' => 'ES',
			'lang' => 'es',
			'theme' => '',
			'timezone' => 99,
			'firstaccess' => 0,
			'lastaccess' => 0,
			'lastlogin' => 0,
			'currentlogin' => 0,
			'lastip' => '',
			'secret' => '',
			'picture' => 0,
			'url' => '',
			'description' => '',
			'descriptionformat' => 1,
			'mailformat' => 1,
			'maildigest' => 0,
			'maildisplay' => 2,
			'autosubscribe' => 1,
			'trackforums' => 0,
			'timecreated' => time(),
			'timemodified' => time(),
			'trustbitmask' => 0,
			'imagealt' => '',
			'calendartype' => 'gregorian',
		];
		// $required = ['firstname', 'lastname'];
		// foreach($required as $r){ if(!array_key_exists($r, $data)){ return false; } } // Faltan datos.
		// $data['password'] = $this->hash_password($data['password']);

		$data = array_merge($default, $data);

		$this->db->insert('user', $data);
		$uid = $this->db->insert_id();

		// Context register

		$this->db->set('contextlevel', 30)
				->set('instanceid', $uid)
				->set('depth', 2);

		$this->db->insert('context');
		$context = $this->db->insert_id();

		$this->db->set('path', '/1/'.$context)->where('id', $context)->update('context');

		// Settings register

		$this->user_settings($uid, 'htmleditor', '');
		$this->user_settings($uid, 'email_bounce_count', '1');
		$this->user_settings($uid, 'email_send_count', '1');
		if($force_changepass){ $this->user_settings($uid, 'auth_forcepasswordchange', '1'); }

		return $uid;
	}

	function user_settings($user, $config, $value = null){
		$this->db->select(['id', 'value'])->where('userid', $user)->where('name', $config);
		$query = $this->db->get('user_preferences');

		if($query->num_rows() == 1){
			if($value === NULL){ return $query->row()->value; }
			else{ return $this->db->set('value', $value)->where('userid', $user)->where('name', $config)->update('user_preferences'); }
		}else{
			if($value === NULL){
				$this->db->set('userid', $user)
					->set('name', $config)
					->set('value', $value)
				->insert('user_preferences');
			}else{ return false; }
		}
	}

	function hash_password($password, $oldhash = false, $fasthash = false) {

		if ($oldhash) {
		    if (isset($CFG->passwordsaltmain)) { return md5($password.$CFG->passwordsaltmain); }
		    else { return md5($password); }
		}

		// Set the cost factor to 4 for fast hashing, otherwise use default cost.
		$options = ($fasthash) ? array('cost' => 4) : array();
		$generatedhash = password_hash($password, PASSWORD_DEFAULT, $options);

		if ($generatedhash === false || $generatedhash === null) {
			return false;
		}

		return $generatedhash;
	}

	function __parse_array_index_id($data, $index = 'id'){
		foreach($data as $d){ $final[$d[$index]] = $d; }
		return $final;
	}

	function get_users_last_access($lastaccess){
    $query = $this->db
          ->where('lastaccess <', $lastaccess)
          ->where('suspended', 0)
        ->get('user');

    if($query->num_rows() > 0){
      return $query->result_array();
    }else{ return array(); }
  }
}

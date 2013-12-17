<?php
	define('SLACKWARE_ROOT', realpath(dirname(__FILE__)."/.."));

	include(SLACKWARE_ROOT."/lib/config.php");
	include(SLACKWARE_ROOT."/lib/service.php");
	include(SLACKWARE_ROOT."/lib/auth.php");

	include(SLACKWARE_ROOT."/lib/smarty/Smarty.class.php");

	if (!file_exists(SLACKWARE_ROOT."/data/templates_c")){
		mkdir(SLACKWARE_ROOT."/data/templates_c");
	}

	$smarty = new Smarty();
	$smarty->template_dir = SLACKWARE_ROOT."/templates";
	$smarty->compile_dir = SLACKWARE_ROOT."/data/templates_c";


	function load_plugins(){

		$GLOBALS['plugins'] = array();

		$dir = SLACKWARE_ROOT."/plugins";

		if ($dh = opendir($dir)){
			while (($file = readdir($dh)) !== false){

				if (is_dir("{$dir}/{$file}") && is_file("{$dir}/{$file}/plugin.php")){

					if ((include("{$dir}/{$file}/plugin.php"))){

						$GLOBALS['plugins'][$file] = 1;
					}
				}
			}
			closedir($dh);
		}

		$GLOBALS['plugins_services'] = array();
		$GLOBALS['plugins_auth'    ] = array();

		foreach ($GLOBALS['plugins'] as $k => $v){
			if (is_subclass_of($k, 'SlackServicePlugin')) $GLOBALS['plugins_services'][$k] = 1;
			if (is_subclass_of($k, 'SlackAuthPlugin'   )) $GLOBALS['plugins_auth'    ][$k] = 1;
		}
	}

	function createPluginInstance($class_name){
		$obj = new $class_name();
		$obj->id = $class_name;

		$obj->smarty = new Smarty();
		$obj->smarty->template_dir = SLACKWARE_ROOT."/plugins/{$class_name}/templates";
		$obj->smarty->compile_dir = SLACKWARE_ROOT."/data/templates_c";
		$obj->smarty->assign_by_ref('this', $obj);

		return $obj;
	}

	function getPluginInstance($iid){

		$icfg = $GLOBALS['data']['instances'][$iid];

		if (!isset($icfg)) return null;

		$plugin = $icfg['plugin'];
		unset($icfg['plugin']);

		$instance = createPluginInstance($plugin);
		$instance->setInstanceConfig($iid, $icfg);

		return $instance;
	}

	function getAuthPlugin($id){

		if (!isset($GLOBALS['plugins_auth'][$id])) return null;

		$instance = createPluginInstance($id);

		$cfg = $GLOBALS['data']['auth'][$id];
		$instance->cfg = $cfg ? $cfg : array();

		return $instance;
	}


	function dumper($foo){
		
		echo "<pre style=\"text-align: left;\">";
		if (is_resource($foo)){
			var_dump($foo);
		}else{
			echo HtmlSpecialChars(var_export($foo, 1));
		}
		echo "</pre>\n";
	}

	function load_data(){
		$data = array();
		$path = SLACKWARE_ROOT."/data/data.php";
		if (!file_exists($path)){
			die("Unable find Slackware data storage");
		}
		include($path);
		$GLOBALS['data'] = $data;
	}

	function save_data(){
		$path = SLACKWARE_ROOT."/data/data.php";

		$fh = fopen($path, 'c');
		if (!$fh) die("Failed to open data file for writing");

		$retries = 5;

		for ($i=0; $i<$retries; $i++){
			$flag = 0;
			$ok = flock($fh, LOCK_EX | LOCK_NB, $flag);
			if ($ok) break;
			if (!$flag) die("Failed to lock data file");
			sleep(1);
		}
		if (!$ok) die("Failed to lock data file");

		ftruncate($fh, 0);
		fwrite($fh, "<"."?php \$data = ".var_export($GLOBALS['data'], true).';');

		flock($fh, LOCK_UN);
		fclose($fh);
	}

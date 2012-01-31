<?php	##################
	#
	#	rah_plugin_installer-plugin for Textpattern
	#	version 0.4
	#	by Jukka Svahn
	#	http://rahforum.biz
	#
	#	Copyright (C) 2011 Jukka Svahn <http://rahforum.biz>
	#	Licensed under GNU Genral Public License version 2
	#	http://www.gnu.org/licenses/gpl-2.0.html
	#
	##################

	if(@txpinterface == 'admin') {
		add_privs('rah_plugin_installer','1');
		add_privs('plugin_prefs.rah_plugin_installer','1,2');
		register_tab('extensions','rah_plugin_installer',gTxt('rah_plugin_installer') == 'rah_plugin_installer' ? 'Plugin Installer' : gTxt('rah_plugin_installer'));
		register_callback('rah_plugin_installer_head','admin_side','head_end');
		register_callback('rah_plugin_installer','rah_plugin_installer');
		register_callback('rah_plugin_installer_options','plugin_prefs.rah_plugin_installer');
		register_callback('rah_plugin_installer_install','plugin_lifecycle.rah_plugin_installer');
	}

/**
	Installer script
	@param $event string Admin-side event.
	@param $step string Admin-side, plugin-lifecycle step.
*/

	function rah_plugin_installer_install($event='', $step='') {
		
		/*
			Uninstall if uninstalling the
			plugin
		*/
		
		if($step == 'deleted') {
			
			@safe_query(
				'DROP TABLE IF EXISTS '.safe_pfx('rah_plugin_installer_def')
			);
			
			safe_delete(
				'txp_prefs',
				"name like 'rah_plugin_installer_%'"
			);
			
			return;
		}
		
		global $prefs, $textarray;
		
		/*
			Make sure language strings are set
		*/
		
		foreach(
			array(
				'' => 'Plugin installer',
				'check_for_updates' => 'Check for updates',
				'name' => 'Name',
				'version' => 'Version',
				'description' => 'Description',
				'installed_version' => 'Installed version',
				'no_plugins' => 'No plugin update definitions downloaded yet.',
				'already_up_to_date' => 'Plugin update definitions are up-to-date.',
				'already_installed' => 'Plugin is already installed.',
				'incorrect_selection' => 'Incorrect selection.',
				'open_ports_or_install_curl' => 'Can not connect to server due to unsupported server configuration. Please either install cURL or set allow_url_fopen directive in PHP configuration file to true.',
				'downloading_plugin_failed' => 'Downloading plugin failed',
				'in_plugin_cache' => 'Initialized from plugin cache',
				'update' => 'Update',
				'download' => 'Download',
				'install' => 'Install',
				'definition_updates_checked' => 'New plugin update definitions downloaded successfully.'
			) as $string => $translation
		) {
			$string = 'rah_plugin_installer' . ($string ? '_' . $string : '');
			
			if(!isset($textarray[$string]))
				$textarray[$string] = $translation;
		}
				
		$version = '0.4';
		
		$current = 
			isset($prefs['rah_plugin_installer_version']) ?
			$prefs['rah_plugin_installer_version'] : '';
		
		if($current == $version)
			return;
		
		if(!$current)
			@safe_query('DROP TABLE IF EXISTS '.safe_pfx('rah_plugin_installer'));
		
		/*
			Stores plugin's version and update details.
			
			* name: Plugin's name. Primary key.
			* author: Author's name.
			* author_uri: Author's website address.
			* version: Plugin's latest version number.
			* description: Plugin's description.
			* help: Cached help file.
			* type: Plugin's type.
			* md5_checksum: MD5 checksum for installer file.
		*/
		
		safe_query(
			"CREATE TABLE IF NOT EXISTS ".safe_pfx('rah_plugin_installer_def')." (
				`name` varchar(64) NOT NULL default '',
				`author` varchar(128) NOT NULL default '',
				`author_uri` varchar(128) NOT NULL default '',
				`version` varchar(10) NOT NULL default '1.0',
				`description` text NOT NULL,
				`help` text NOT NULL,
				`type` int(2) NOT NULL default 0,
				`md5_checksum` varchar(32) NOT NULL default '',
				PRIMARY KEY(`name`)
			) PACK_KEYS=1 CHARSET=utf8"
		);
		
		/*
			Add preferences strings
		*/
		
		foreach(
			array(
				'version' => $version,
				'updated' => 0,
				'checksum' => ''
			) as $name => $value
		) {
			if(!isset($prefs['rah_plugin_installer_'.$name]) || $name == 'version') {
				set_pref('rah_plugin_installer_'.$name,$value,'rah_pins',2,'',0);
				$prefs['rah_plugin_installer_'.$name] = $value;
			}
		}
	}

/**
	Delivers the panes
*/

	function rah_plugin_installer() {
		require_privs('rah_plugin_installer');
		require_privs('plugin');
		
		rah_plugin_installer_install();
		
		global $step;
		
		$steps = 
			array(
				'download' => true,
				'update' => true,
			);
		
		if(!$step || !bouncer($step, $steps))
			$step = 'list';
		
		$func = 'rah_plugin_installer_' . $step;
		$func();
	}

/**
	The main pane; the plugin listing
	@param $message string Activity message.
	@param $check bool Check for updates.
*/

	function rah_plugin_installer_list($message='',$check=false) {
		
		global $event;
		
		pagetop('Plugin Installer',$message);
		
		if(($updates = rah_plugin_installer_check($check)) && $updates)
			$updates = gTxt('rah_plugin_installer_' . $updates);
		
		$installed = rah_plugin_installer_installed();
		
		$rs = 
			safe_rows(
				'name, version, description',
				'rah_plugin_installer_def',
				'1=1'
			);
		
		$out[] =
		
			'	<div id="rah_plugin_installer_container" class="rah_ui_container">'.n.
			
			'		<p class="rah_ui_nav">'.
						'<span class="rah_ui_sep">&#187;</span> '.
						'<a id="rah_plugin_installer_update" href="?event='.$event.'&amp;step=update&amp;_txp_token='.form_token().'">'.
							gTxt('rah_plugin_installer_check_for_updates').
						'</a>'.
			'		</p>'.
			
			($updates && $rs ? '		<p id="warning">'.$updates.'</p>' : '').
			
			'		<table cellspacing="0" cellpadding="0" id="list">'.n.
			'			<thead>'.n.
			'				<tr>'.n.
			'					<th>'.gTxt('rah_plugin_installer_name').'</th>'.n.
			'					<th>'.gTxt('rah_plugin_installer_version').'</th>'.n.
			'					<th>'.gTxt('rah_plugin_installer_description').'</th>'.n.
			'					<th>'.gTxt('rah_plugin_installer_installed_version').'</th>'.n.
			'					<th>&#160;</th>'.n.
			'				</tr>'.n.
			'			</thead>'.n.
			'			<tbody>'.n;
		
		if($rs) {
			foreach($rs as $a) {

				$action = 'install';
				$ins = '&#160;';
			 	
			 	if(isset($installed[$a['name']])) {
			 		$ins = $installed[ $a['name']];
			 		$action = $ins == $a['version'] ? '' : 'update';
			 	}
			
				$out[] = 
					'				<tr>'.n.
					'					<td>'.htmlspecialchars($a['name']).'</td>'.n.
					'					<td>'.htmlspecialchars($a['version']).'</td>'.n.
					'					<td>'.htmlspecialchars($a['description']).'</td>'.n.
					'					<td>'.$ins.'</td>'.n.
					'					<td>'.($action ? '<a href="?event='.$event.'&amp;step=download&amp;name='.$a['name'].'&amp;_txp_token='.form_token().'">'.gTxt('rah_plugin_installer_'.$action).'</a>' : '&#160;').'</td>'.n.
					'				</tr>'.n;
				}
		} else
			$out[] =
				'			<tr>'.n.
				'				<td colspan="5">'.($updates ? $updates : gTxt('rah_plugin_installer_no_plugins')).'</td>'.n.
				'			</tr>'.n;
		
		$out[] = 
			
			'			</tbody>'.n.
			'		</table>'.n.
			'	</div>'.n;
			
		echo implode('', $out);
	}

/**
	Get list of installed plugins
*/

	function rah_plugin_installer_installed() {

		static $cache = NULL;
		
		if($cache !== NULL) {
			return $cache;
		}
		
		$cache = array();
			
		$rs = 
			safe_rows(
				'name, version',
				'txp_plugin',
				'1=1'
			);
			
		foreach($rs as $a)
			$cache[$a['name']] = $a['version'];
		
		return $cache;
	}

/**
	Checks for updates
	@param $manual bool If user-launched update check, or auto.
	@return string Returned message as a language string.
*/

	function rah_plugin_installer_check($manual=false) {
		
		global $prefs;
		
		@$disabled = !ini_get('allow_url_fopen') && !function_exists('curl_init');
		
		if($disabled)
			return 'open_ports_or_install_curl';
		
		$now = strtotime('now');
		
		$wait = !$manual ? 604800 : 1800;
		
		if($prefs['rah_plugin_installer_updated'] + $wait >= $now) {
			return $manual ? 'already_up_to_date' : '';
		}
		
		$def = rah_plugin_installer_fget('http://rahforum.biz/?rah_plugin_installer=1&rah_version=2' , $manual ? 30 : 5);
		
		/*
			Update the last-update timestamp if we got payload
		*/
		
		if($def) {
			
			safe_update(
				'txp_prefs',
				"val='$now'",
				"name='rah_plugin_installer_updated'"
			);
		
			$prefs['rah_plugin_installer_updated'] = $now;
		}
		
		if(!$def || !preg_match('!^[a-zA-Z0-9/+]*={0,2}$!',$def)) {
			return 'could_not_fetch';
		}
		
		$def = base64_decode($def);
		$md5 = md5($def);
		
		if($md5 == $prefs['rah_plugin_installer_checksum'])
			return 'already_up_to_date';
		
		safe_update(
			'txp_prefs',
			"val='$md5'",
			"name='rah_plugin_installer_checksum'"
		);
		
		rah_plugin_installer_import(rah_plugin_installer_parser($def));
		
		return 'definition_updates_checked';
	}

/**
	Fire manual listing refresh
*/

	function rah_plugin_installer_update() {
		rah_plugin_installer_list('',true);
	}

/**
	Parses update file
	@param $file string File to parse.
	@return array
*/

	function rah_plugin_installer_parser($file) {

		$file = explode(n, $file);
		$plugin = '';
		$out = array();

		foreach($file as $line) {
			
			$line = trim($line);
			
			if(!$line || strpos($line,'#') === 0)
				continue;

			/*
				Set the plugin name
			*/
			
			if(strpos($line,'@') === 0 && strpos($line,'_') == 4) {
				$plugin = substr($line, 1);
				continue;
			}
			
			if(!$plugin)
				continue;
			
			if(!preg_match('/^(\w+)\s*=>\s*(.+)$/', $line, $m))
				continue;
				
			if(empty($m[1]) || empty($m[2]))
				continue;
					
			$out[$plugin][$m[1]] = $m[2];
		}
		
		return $out;
	}
	
/**
	Imports update file to the database
	@param $inc array Definitions to import.
	@return Nothing.
*/

	function rah_plugin_installer_import($inc) {
		
		$plugin = array();
		
		$rs = 
			safe_rows(
				'name, version',
				'rah_plugin_installer_def',
				'1=1'
			);
		
		foreach($rs as $a)
			$plugin[$a['name']] = $a['version'];
		
		foreach($inc as $name => $a) {
			
			if(!isset($a['description']) || !isset($a['version']))
				continue;
			
			if(!isset($plugin[$name])) {
				
				safe_insert(
					'rah_plugin_installer_def',
					"name='".doSlash($name)."',
					version='".doSlash($a['version'])."',
					description='".doSlash($a['description'])."'"
				);
					
			}
			else if($plugin[$name] != $a['version']) {
			
				safe_update(
					'rah_plugin_installer_def',
					"version='".doSlash($a['version'])."',
					description='".doSlash($a['description'])."'",
					"name='".doSlash($name)."'"
				);
				
			}
			
			unset($plugin[$name]);
		}
		
		if(!empty($plugin) && is_array($plugin)) {
			
			foreach($plugin as $name => $version)
				$remove[] = "'".doSlash($name)."'";
				
			safe_delete(
				'rah_plugin_installer_def',
				'name in('. implode(',', $remove) . ')'
			);
			
		}
		
	}

/**
	Download the plugin code and run Textpattern's plugin installer
*/

	function rah_plugin_installer_download() {
		
		@$disabled = !ini_get('allow_url_fopen') && !function_exists('curl_init');
		
		if($disabled) {
			rah_plugin_installer_list('open_ports_or_install_curl');
			return;
		}
		
		$name = gps('name');
		
		$def = 
			safe_row(
				'name, version',
				'rah_plugin_installer_def',
				"name='".doSlash($name)."' LIMIT 0, 1"
			);
		
		if(!$name || !$def) {
			rah_plugin_installer_list('rah_plugin_installer_incorrect_selection');
			return;
		}
		
		if(fetch('version', 'txp_plugin', 'name', $name) == $def['version']) {
			rah_plugin_installer_list('rah_plugin_installer_already_installed');
			return;
		}
		
		$url = 'http://rahforum.biz/?rah_plugin_download='.$name;
		$url = function_exists('gzencode') ? $url . '&rah_type=zip' : $url;
			
		$plugin = rah_plugin_installer_fget($url);
		
		if(empty($plugin)) {
			rah_plugin_installer_list('rah_plugin_installer_downloading_plugin_failed');
			return;
		}

		$_POST['install_new'] = 'Upload';	
		$_POST['plugin'] = $plugin;
		$_POST['plugin64'] = $plugin;
		$_POST['event'] = 'plugin';
		$_POST['step'] = 'plugin_verify';
		$_POST['_txp_token'] = form_token();
		
		$step = 'plugin_verify';
		$event = 'plugin';
		
		include_once txpath.'/include/txp_plugin.php';
		exit;
	}

/**
	Downloads remote file
	@param $url string URL to download.
	@param $timeout int Connection timeout in seconds.
	@return string Contents of the file. False on failure.
*/

	function rah_plugin_installer_fget($url, $timeout=10) {
		
		/*
			If cURL isn't available,
			use file_get_contents if possible
		*/
			
		if(!function_exists('curl_init')) {
			
			if(!ini_get('allow_url_fopen'))
				return false;
			
			$context = 
				stream_context_create(
					array(
					'http' => 
						array(
							'timeout' => $timeout
						)
					)
				);

			@$file = file_get_contents($url, 0, $context);
			return !$file ? false : trim($file);
		}
		
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_FAILONERROR, 1);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		$file = curl_exec($ch);
		$http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);
		
		return $file !== false && $http == '200' ? trim($file) : false;
	}

/**
	Redirect to the admin-side interface
*/

	function rah_plugin_installer_options() {
		header('Location: ?event=rah_plugin_installer');
		echo 
			'<p>'.n.
			'	<a href="?event=rah_plugin_installer">'.gTxt('continue').'</a>'.n.
			'</p>';
	}

/**
	Adds styles to the <head>
*/

	function rah_plugin_installer_head() {
		global $event;
		
		if($event != 'rah_plugin_installer')
			return;
		
		echo <<<EOF
			<style type="text/css">
				#rah_plugin_installer_container {
					width: 950px;
					margin: 0 auto;	
				}
				#rah_plugin_installer_container table {
					width: 100%;	
				}
			</style>
EOF;
	}
?>
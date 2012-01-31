<?php	##################
	#
	#	rah_plugin_installer-plugin for Textpattern
	#	version 0.2
	#	by Jukka Svahn
	#	http://rahforum.biz
	#
	###################

	if (@txpinterface == 'admin') {
		add_privs('rah_plugin_installer','1');
		register_tab('extensions', 'rah_plugin_installer', 'Plugin Installer');
		register_callback('rah_plugin_installer', 'rah_plugin_installer');
	}

	function rah_plugin_installer() {
		require_privs('rah_plugin_installer');
		global $step;
		rah_plugin_installer_install();
		if(in_array($step,array(
			'rah_plugin_installer_download',
			'rah_plugin_installer_settings',
			'rah_plugin_installer_save',
			'rah_plugin_installer_update',
		))) $step();
		else rah_plugin_installer_list();
	}

	function rah_plugin_installer_settings($message='') {
		
		pagetop('Settings',$message);
		
		global $event;
		
		$prefs = rah_plugin_installer_prefs();
		
		echo 
			n.
			'	<form method="post" action="index.php" style="width:950px;margin:0 auto;">'.n.
			
			'		<h1><strong>rah_plugin_installer</strong> | Preferences</h1>'.n.
			
			'		<p>&#187; <a href="?event='.$event.'">Back to the main panel</a></p>'.n.
			
			'		<input type="hidden" name="event" value="'.$event.'" />'.n.
			'		<input type="hidden" name="step" value="rah_plugin_installer_save" />'.n.
			
			'		<p>'.
			'			<label for="rah_plugin_installer_compress">'.n.
			'				<strong>Download uncompressed or compressed files.</strong> '.
							'This setting defines which packages you are downloading and installing when updating or installing. '.
							'While uncompressed version requires more bandwidth and might overcome your server\'s memory restrictions, '.
							'compressed version requires that the server uncompresses the file and has zlib installed. You don\'t need to worry about this setting '.
							'if everything works fine.'.n.
			'			</label>'.n.
			'		</p>'.n.
			'		<p>'.n.
			'			<select style="width:650px;" id="rah_plugin_installer_compress" name="compress">'.n.
			'				<option value="0"'.(($prefs['compress'] == 0) ? ' selected="selected"' : '').'>Download and install normal uncompressed versions</option>'.n.
			'				<option value="1"'.(($prefs['compress'] == 1) ? ' selected="selected"' : '').'>Download and install compressed versions</option>'.n.
			'			</select>'.n.
			'		</p>'.n.
			
			'		<p>'.n.
			'			<label for="rah_plugin_installer_autoupdates">'.n.
			'				<strong>Auto-updater.</strong> Toggle auto-updates on or off. If auto-updates are off, you can still check updates manually with the <em>"Check updates now"</em> feature.'.n.
			'			</label>'.n.
			'		</p>'.n.
			'		<p>'.n.
			'			<select style="width:650px;" id="rah_plugin_installer_autoupdates" name="autoupdates">'.n.
			'				<option value="1"'.(($prefs['autoupdates'] == 1) ? ' selected="selected"' : '').'>Check for updates automatically time to time</option>'.n.
			'				<option value="0"'.(($prefs['autoupdates'] == 0) ? ' selected="selected"' : '').'>Turn auto-updater off, only check updates manually</option>'.n.
			'			</select>'.n.
			
			'		</p>'.n.
			'		<input type="submit" value="Save" class="publish" />'.n.
			'	</form>'.n;
		;
		
	}

	function rah_plugin_installer_save() {
		
		$prefs = array(
			'autoupdates',
			'compress'
		);
		
		foreach($prefs as $val) {
			safe_update(
				'rah_plugin_installer',
				"code='".doSlash(ps($val))."'",
				"name='".doSlash($val)."'"
			);
		}
		
		rah_plugin_installer_settings('Preferences saved.');
	}

	function rah_plugin_installer_check_updates($prefs) {
		global $step;
		
		if(
			(empty($prefs['updated']) or (strtotime($prefs['updated'].' '.$prefs['time_interval']) < strtotime('now'))) && 
			($prefs['autoupdates'] == 1 or $step == 'rah_plugin_installer_update')
		) {

			$context = 
				stream_context_create(array(
					'http' => array('timeout' => $prefs['timeout'])
				));

			@$content = 
				file_get_contents($prefs['update_file'],0,$context);
			
			if(empty($content)) 
				return 1;
			
			else {
				$content = base64_decode($content);
				
				if($content != $prefs['content']) {
					$up1 = 
						safe_update(
							'rah_plugin_installer',
							"code='".doSlash($content)."'",
							"name='content'"
						);
				}
				
				$now = doSlash(safe_strftime('%Y-%m-%d %H:%M:%S'));
				
				$up2 = 
					safe_update(
						'rah_plugin_installer',
						"code='$now'",
						"name='updated'"
					);
				
				if((isset($up1) && $up1 == false) or $up2 == false) 
					return 2;
				if($content != $prefs['content']) 
					return 3;
				else 
					return 4;
				
			}
		}
		return;
	}

	function rah_plugin_installer_list($message='') {
		global $event;
		
		pagetop('Plugin Installer',$message);
		
		$prefs = rah_plugin_installer_prefs();
		
		/*
			Check for new content/updates
		*/
		
		$msgs = 
			array(
				'Checking updates... Error: Can\'t fetch the updates from the server. Check your server\'s connections and/or that your host allows outgoing connections, and that allow_url_fopen is set to true.',
				'Updates checked. Error while writing updates to your database.',
				'Updates checked and found.',
				'Updates checked. No new updates found.'
			);
		
		$msg = rah_plugin_installer_check_updates($prefs);
		
		if(!empty($msg))
			$prefs['content'] = 
				fetch(
					'code',
					'rah_plugin_installer',
					'name',
					'content'
				);
		
		$table = 
			rah_plugin_installer_table(
				$prefs
			);
		
		echo 
			
			'	<div style="width:950px;margin:0 auto;">'.n.
			'		<h1><strong>rah_plugin_installer</strong> | Install plugins</h1>'.n.
			
			'		<p>'.
						' &#187; <a href="?event='.$event.'&amp;step=rah_plugin_installer_update">Check for updates</a> '.
						' &#187; <a href="?event='.$event.'&amp;step=rah_plugin_installer_settings">Preferences</a> '.
						' &#187; <a href="?event=plugin&amp;step=plugin_help&amp;name=rah_plugin_installer">Documentation</a> '.
			'		</p>'.
			
			(($msg) ? '		<p id="warning">'.$msgs[$msg-1].'</p>' : '').
			
			'		<table cellspacing="0" cellpadding="0" class="list" id="list" style="width:100%;">'.n.
			'			<tr>'.n.
			'				<th>'.gTxt('name').'</th>'.n.
			'				<th>'.gTxt('version').'</th>'.n.
			'				<th>'.gTxt('description').'</th>'.n.
			'				<th>Installed</th>'.n.
			'				<th>&#160;</th>'.n.
			'			</tr>'.n;
		
		if($table)
			echo $table;
		else 
			echo 
			'			<tr>'.n.
			'				<td colspan="5">No plugins found.</td>'.n.
			'			</tr>'.n;
		
		echo 
			
			'		</table>'.n.
			
			'	</div>'.n;
			
			
	}

	function rah_plugin_installer_update() {
		
		$prefs = rah_plugin_installer_prefs();
		
		if(
			!empty($prefs['updated']) && (strtotime($prefs['updated'].' '.$prefs['mupd_interval']) < strtotime('now'))
		) 
			safe_update(
				"rah_plugin_installer",
				"code=''",
				"name='updated'"
			);
		
		rah_plugin_installer_list();
	}

	function rah_plugin_installer_install() {
		safe_query(
			"CREATE TABLE IF NOT EXISTS ".safe_pfx('rah_plugin_installer')." (
				`name` VARCHAR(255) NOT NULL,
				`code` LONGTEXT NOT NULL,
			PRIMARY KEY(`name`))"
		);
		
		rah_plugin_installer_prefs_insert(
			array(
				'site' => 'http://rahforum.biz/',
				'update_file' => 'http://rahforum.biz/?rah_plugin_installer=1',
				'download_uri' => 'http://rahforum.biz/?rah_plugin_download=[NAME]',
				'download_zip' => 'http://rahforum.biz/?rah_plugin_download=[NAME]&rah_type=zip',
				'download_timeout' => '10',
				'info_uri' => 'http://rahforum.biz/plugins/[NAME]',
				'time_interval' => '+14 days',
				'mupd_interval' => '+30 minutes',
				'timeout' => '3',
				'updated' => '',
				'content' => '',
				'compress' => '0',
				'autoupdates' => '1'
			)
		);
	}

	function rah_plugin_installer_prefs() {
		
		$out = array();
		
		$rs = 
			safe_rows(
				'name,code',
				'rah_plugin_installer',
				'1=1'
			);
		
		foreach($rs as $a) 
			$out[$a['name']] = $a['code'];
		
		return $out;
		
	}

	function rah_plugin_installer_prefs_insert($array) {
		
		foreach($array as $key => $val) {
			
			if(
				safe_count(
					'rah_plugin_installer',
					"name='".doSlash($key)."'"
				) == 0
			)
				safe_insert(
					'rah_plugin_installer',
					"name='".doSlash($key)."',code='".doSlash($val)."'"
				);
			
		}
	}

	function rah_plugin_installer_table($prefs,$installing='') {
		
		if(empty($prefs['content']))
			return '';
		
		$rows = explode('[row]',$prefs['content']);
		
		$rs = 
			safe_rows(
				'version,name',
				'txp_plugin',
				'1=1'
			);
		
		foreach($rs as $a)
			$plugin[$a['name']] = $a['version'];
		
		$out = array();
		
		foreach($rows as $row) {
			
			$field = explode('|',$row);
			
			if(!isset($field[2]))
				continue;
			
			$name = trim($field[0]);
			$version = trim($field[2]);
			$description = trim($field[1]);
			
			if(empty($name) or empty($version) or empty($description))
				continue;
			
			$installed = isset($plugin[$name]) ? (($plugin[$name]) ? $plugin[$name] : '0.1') : '';
			
			if(!empty($installing) && $name == $installing && $installed != $version) {
				return true;
			}
			
			if(!empty($installing))
				continue;
			
			$name = htmlspecialchars($name);
			
			if($installed) 
				$form = ($installed != $version) ? rah_plugin_installer_link(gTxt('update'),$name) : '&#160;';
			else 
				$form = rah_plugin_installer_link(gTxt('install'),$name);
			
			
			$url = 
				str_replace(
					'[NAME]',
					$name,
					$prefs['info_uri']
				);
		
			$out[] = 
				'	<tr>'.n.
				'		<td><a target="_blank" href="'.$url.'">'.$name.'</a></td>'.n.
				'		<td>'.htmlspecialchars($version).'</td>'.n.
				'		<td>'.htmlspecialchars($description).'</td>'.n.
				'		<td>'.(($installed) ? $installed : '&#160;').'</td>'.n.
				'		<td>'.$form.'</td>'.n.
				'	</tr>'.n;
		}
		
		return implode('',$out);

	}

	function rah_plugin_installer_link($label='',$plugin='') {
		global $event;
		return 
			'<a href="?event='.$event.'&amp;step=rah_plugin_installer_download&amp;plugin_name='.$plugin.'">'.$label.'</a>'
		;
	}

	function rah_plugin_installer_download() {
		
		$name = trim(gps('plugin_name'));
		$prefs = rah_plugin_installer_prefs();
		
		if(empty($name)) {
			rah_plugin_installer_list('Incorrect selection.');
			return;
		}

		if(rah_plugin_installer_table($prefs,$name) != true) {
			rah_plugin_installer_list('Already installed or false selection.');
			return;
		}
		
		if($prefs['compress'] == 1)
			$uri = $prefs['download_zip'];
		else 
			$uri = $prefs['download_uri'];

		$url = 
			str_replace(
				'[NAME]',
				$name,
				$uri
			);
		
		$context = 
			stream_context_create(array(
				'http' => array('timeout' => $prefs['download_timeout'])
			));

		@$plugin = file_get_contents($url, 0, $context);
		
		if(empty($plugin)) {
			rah_plugin_installer_list('Downloading the plugin failed: unable to get the file.');
			return;
		}

		$_POST['install_new'] = 'Upload';	
		$_POST['plugin'] = $plugin;
		$_POST['plugin64'] = $plugin;
		$_POST['event'] = 'plugin';
		$_POST['step'] = 'plugin_verify';
		$step = 'plugin_verify';
		$event = 'plugin';
		
		include_once txpath.'/include/txp_plugin.php';
		exit;
	}
?>
<?php	##################
	#
	#	rah_plugin_installer-plugin for Textpattern
	#	version 0.1
	#	by Jukka Svahn
	#	http://rahforum.biz
	#
	###################

	if (@txpinterface == 'admin') {
		add_privs('rah_plugin_installer','1');
		register_tab("extensions", "rah_plugin_installer", "Plugin Installer");
		register_callback("rah_plugin_installer", "rah_plugin_installer");
	}
	
	function rah_plugin_installer() {
		global $step;
		if(in_array($step,array(
			'rah_plugin_installer_install',
			'rah_plugin_installer_update'
		))) $step();
		else rah_plugin_installer_list();
	}

	function rah_plugin_installer_list($message='') {
		require_privs('rah_plugin_installer');
		rah_plugin_installer_setup();
		pagetop('Plugin Installer',$message);
		$url = 'http://rahforum.biz/';
		$updated = fetch('updated','rah_plugin_installer','name','table');
		if(strtotime($updated.' +14 days') < strtotime('now')) {
			$content = @file_get_contents($url.'?rah_plugin_installer=1');
			if($content) {
				$content = doSlash(base64_decode($content));
				safe_update(
					"rah_plugin_installer",
					"code='$content',updated=now()",
					"name='table'"
				);
			}
		}
		echo rah_plugin_installer_table($url);
	}

	function rah_plugin_installer_update() {
		if(ps('update-table') == 1) 
			safe_update(
				"rah_plugin_installer",
				"updated=''",
				"name='table'"
			);
		$message = (ps('update-table') == 1) ? 'Plugin table synched with server' : '';
		rah_plugin_installer_list($message);
	}

	function rah_plugin_installer_table($url='') {
		$table = array();
		$i = 0;
		$code = fetch('code','rah_plugin_installer','name','table');
		$row = explode('[row]',$code);
		$rows = count($row)-1;
		$is = implode('',$row);
		if($is) {
			while ($i < $rows) {
				$field = explode('|',$row[$i]);
				$name = trim($field[0]);
				$description = trim($field[1]);
				$version = trim($field[2]);
				$installed = (fetch('name','txp_plugin','name',doSlash($name))) ? fetch('version','txp_plugin','name',doSlash($name)) : '';
				$table[] = 
					'	<tr>'.n.
					'		<td><a target="_blank" href="'.$url.'plugins/'.$name.'">'.$name.'</a></td>'.n.
					'		<td>'.$version.'</td>'.n.
					'		<td>'.$description.'</td>'.n.
					'		<td>'.(($installed) ? $installed : gTxt('none')).'</td>'.n.
					'		<td>'.((!$installed) ? rah_plugin_installer_form(gTxt('install'),$name) : (($installed < $version) ? rah_plugin_installer_form(gTxt('update'),$name) : '&#160;')).'</td>'.n.
					'	</tr>'.n;
				$i++;
			}
		}
		return n.
			'	<div style="width:950px;margin:0 auto;">'.n.
			'		<h1><strong>rah_plugin_installer</strong> | Install rah-plugins</h1>'.n.
			'		<table cellspacing="0" cellpadding="0" class="list" id="list" style="width:100%;">'.n.
			'			<tr>'.n.
			'				<th>'.gTxt('name').'</th>'.n.
			'				<th>'.gTxt('version').'</th>'.n.
			'				<th>'.gTxt('description').'</th>'.n.
			'				<th>Installed</th>'.n.
			'				<th>&#160;</th>'.n.
			'			</tr>'.n.implode('',$table).
			'		</table>'.n.
			'		<form method="post" action="index.php">'.n.
			'			<fieldset style="padding:20px;margin:20px 0;">'.n.
			'				<legend>Fetch and update plugin list right now from servers</legend>'.n.
			'				<label>'.n.
			'					Lets do it? '.n.
			'					<select name="update-table">'.n.
			'						<option value="0">'.gTxt('no').'</option>'.n.
			'						<option value="1">'.gTxt('yes').'</option>'.n.
			'					</select>'.n.
			'				</label>'.n.
			'				<input type="submit" value="'.gTxt('update').'" class="smallerbox" />'.n.
			'				<input type="hidden" name="step" value="rah_plugin_installer_update">'.n.
			'				<input type="hidden" name="event" value="rah_plugin_installer" />'.n.
			'			</fieldset>'.n.
			'		</form>'.n.
			'	</div>'.n;
	}

	function rah_plugin_installer_form($label='',$plugin='') {
		return 
			'<form method="post" action="index.php">'.
			'<input type="submit" value="'.$label.'" name="install" class="smallerbox" />'.
			'<input type="hidden" value="rah_plugin_installer" name="event" />'.
			'<input type="hidden" value="rah_plugin_installer_install" name="step" />'.
			'<input type="hidden" value="'.$plugin.'" name="plugin_name" />'.
			'</form>';
	}
	
	function rah_plugin_installer_install() {
		$url = 'http://rahforum.biz/';
		$download_url = $url.'?rah_plugin_download=';
		$plugin = @file_get_contents($download_url.ps('plugin_name'));
		if ($plugin) {
			$_POST['install_new'] = 'Upload';	
			$_POST['plugin'] = $plugin;
			$_POST['plugin64'] = $plugin;
			$_POST['event'] = 'plugin';
			$_POST['step'] = 'plugin_verify';
			$step = 'plugin_verify';
			$event = 'plugin';
			include txpath.'/include/txp_plugin.php';
			exit;
		} else rah_plugin_installer_list('Uploading plugin failed: server timed out.');
	}

	function rah_plugin_installer_setup () {
		safe_query(
			"CREATE TABLE IF NOT EXISTS ".safe_pfx('rah_plugin_installer')." (
				`name` VARCHAR(255) NOT NULL,
				`code` LONGTEXT NOT NULL,
				`updated` DATETIME NOT NULL,
			PRIMARY KEY(`name`))"
		);
		if(safe_count('rah_plugin_installer',"name='table'") == 0) safe_insert('rah_plugin_installer',"name='table'");
	}
?>
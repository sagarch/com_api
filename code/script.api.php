<?php
/**
 * @package   AdminTools
 * @copyright Copyright (c)2010-2014 Nicholas K. Dionysopoulos
 * @license   GNU General Public License version 3, or later
 * @version   $Id$
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

defined('_JEXEC') or die();

jimport('joomla.filesystem.folder');
jimport('joomla.filesystem.file');
jimport('joomla.application.component.controller');

if (!defined('DS'))
{
	define('DS', DIRECTORY_SEPARATOR);
}

class Com_ApiInstallerScript
{
	// Used to identify new install or update
	private $componentStatus = "install";

	private $installation_queue = array(
		// modules => { (folder) => { (module) => { (position), (published) } }* }*
		'modules'=>array(
			'admin'=>array(),
			'site'=>array()
		),

		// plugins => { (folder) => { (element) => (published) }* }*
		'plugins'=>array(
			'system'=>array(
			)
		),

		// libraries
		'libraries'=>array()
	);

	private $uninstall_queue = array(
		// modules => { (folder) => { (module) => { (position), (published) } }* }*
		'modules'=>array(
			'admin'=>array(),
			'site'=>array()
		),

		// plugins => { (folder) => { (element) => (published) }* }*
		'plugins'=>array(
			'system'=>array()
		),

		// libraries
		'libraries'=>array()
	);

	/**
	 * method to run before an install/update/uninstall method
	 *
	 * @return void
	 */
	function preflight($type, $parent)
	{
	}

	/**
	 * Runs after install, update or discover_update
	 * @param string $type install, update or discover_update
	 * @param JInstaller $parent
	 */
	function postflight( $type, $parent )
	{
		$msgBox = array();

		// Install subextensions
		$status = $this->_installSubextensions($parent);

		// Install Techjoomla Straper
		$straperStatus = $this->_installStraper($parent);

		if (version_compare(JVERSION, '3.0', 'lt'))
		{
			$document = JFactory::getDocument();
			$document->addStyleSheet(JUri::root() . '/media/techjoomla_strapper/css/bootstrap.min.css');
		}

		// Show the post-installation page
		$this->_renderPostInstallation($status, $straperStatus, $parent, $msgBox);
	}

	/**
	 * method to install the component
	 *
	 * @return void
	 */
	function install($parent)
	{
	}

	/**
	 * Runs on uninstallation
	 *
	 * @param JInstaller $parent
	 */
	function uninstall($parent)
	{
	}

	/**
	 * method to update the component
	 *
	 * @return void
	 */
	function update($parent)
	{
		$this->componentStatus = "update";
		// From 1.6.1
		$this->fix_db_on_update();
	}
	
	//Since API version 1.6.1
	function fix_db_on_update()
	{

		$db = JFactory::getDBO();
		$config = JFactory::getConfig();


		$query="SHOW COLUMNS FROM #__api_keys WHERE `Field` = 'state'";
		$db->setQuery($query);
		$check=$db->loadResult();
		if(!$check)
		{
			echo $query="ALTER TABLE  `#__api_keys` ADD  `state` tinyint(1)	 NOT NULL";
			$db->setQuery($query);
			if ( !$db->execute() ) {
				JError::raiseError( 500, $db->stderr() );
			}
		}


		$query="SHOW COLUMNS FROM #__api_keys WHERE `Field` = 'checked_out'";
		$db->setQuery($query);
		$check=$db->loadResult();
		if(!$check)
		{
			$query="ALTER TABLE  `#__api_keys` ADD  `checked_out` int(11)	 NOT NULL  AFTER  `state`";
			$db->setQuery($query);
			if ( !$db->execute() ) {
				JError::raiseError( 500, $db->stderr() );
			}
		}


		$query="SHOW COLUMNS FROM #__api_keys WHERE `Field` = 'checked_out_time'";
		$db->setQuery($query);
		$check=$db->loadResult();
		if(!$check)
		{
			$query="ALTER TABLE  `#__api_keys` ADD  `checked_out_time` datetime	 NOT NULL DEFAULT '0000-00-00 00:00:00'  AFTER  `checked_out`";
			$db->setQuery($query);
			if ( !$db->execute() ) {
				JError::raiseError( 500, $db->stderr() );
			}
		}

		$query="SHOW COLUMNS FROM #__api_keys WHERE `Field` = 'created'";
		$db->setQuery($query);
		$check=$db->loadResult();
		if(!$check)
		{
			$query="ALTER TABLE  `#__api_keys` ADD  `created` datetime	 NOT NULL  AFTER  `checked_out_time`";
			$db->setQuery($query);
			if ( !$db->execute() ) {
				JError::raiseError( 500, $db->stderr() );
			}
		}

		$query="SHOW COLUMNS FROM #__api_keys WHERE `Field` = 'created'";
		$db->setQuery($query);
		$check=$db->loadResult();
		if(!$check)
		{
			$query="ALTER TABLE  `#__api_keys` ADD  `created` datetime	 NOT NULL DEFAULT '0000-00-00 00:00:00'  AFTER  `checked_out_time`";
			$db->setQuery($query);
			if ( !$db->execute() ) {
				JError::raiseError( 500, $db->stderr() );
			}
		}

		$query="SHOW COLUMNS FROM #__api_keys WHERE `Field` = 'user_id'";
		$db->setQuery($query);
		$check=$db->loadResult();
		if($check)
		{
			$query="ALTER TABLE `#__api_keys` CHANGE `user_id` `userid` INT(11)";
			$db->setQuery($query);
			if ( !$db->execute() ) {
				JError::raiseError( 500, $db->stderr() );
			}
		}

		$query="SHOW COLUMNS FROM #__api_keys WHERE `Field` = 'last_used'";
		$db->setQuery($query);
		$check=$db->loadResult();
		if(!$check)
		{
			$query="ALTER TABLE  `#__api_keys` ADD  `last_used` datetime	 NOT NULL DEFAULT '0000-00-00 00:00:00' ";
			$db->setQuery($query);
			if ( !$db->execute() ) {
				JError::raiseError( 500, $db->stderr() );
			}
		}


		$query="SHOW COLUMNS FROM #__api_keys WHERE `Field` = 'per_hour'";
		$db->setQuery($query);
		$check=$db->loadResult();
		if(!$check)
		{
			$query="ALTER TABLE  `#__api_keys` ADD  `per_hour` int(11)	 NOT NULL  AFTER  `last_used`";
			$db->setQuery($query);
			if ( !$db->execute() ) {
				JError::raiseError( 500, $db->stderr() );
			}
		}

		$query="SHOW COLUMNS FROM #__api_logs WHERE `Field` = 'post_data'";
		$db->setQuery($query);
		$check=$db->loadResult();
		if(!$check)
		{
			$query="ALTER TABLE  `#__api_logs` ADD  `post_data` TEXT NOT NULL ";
			$db->setQuery($query);
			if ( !$db->execute() ) {
				JError::raiseError( 500, $db->stderr() );
			}
		}



	}

	/**
	 * Renders the post-installation message
	 */
	private function _renderPostInstallation($status, $straperStatus, $parent, $msgBox=array())
	{
		if (version_compare(JVERSION, '3.0', 'lt'))
		{
			$document = JFactory::getDocument();
			$document->addStyleSheet(JUri::root() . '/media/techjoomla_strapper/css/bootstrap.min.css');
		}

		$enable = "<span class=\"label label-success\">Enabled</span>";
		$disable = "<span class=\"label label-important\">Disabled</span>";
		$updatemsg = "Updated Successfully";
		?>
		<?php $rows = 1;?>
		<div class="techjoomla-bootstrap" >
			<table class="table-condensed table">
				<thead>
					<tr class="row1">
						<th class="title" colspan="2">Extension</th>
						<th width="30%">Status</th>
					</tr>
				</thead>

				<tfoot>
					<tr>
						<td colspan="3"></td>
					</tr>
				</tfoot>

				<tbody>
					<tr class="row2">
						<td class="key" colspan="2"><strong>TJFields component</strong></td>
						<td><strong style="color: green">Installed</strong></td>
					</tr>
					<tr class="row2">
						<td class="key" colspan="2">
							<strong>TechJoomla Strapper <?php echo $straperStatus['version']?></strong> [<?php echo $straperStatus['date'] ?>]
						</td>
						<td>
							<strong>
								<span style="color: <?php echo $straperStatus['required'] ? ($straperStatus['installed']?'green':'red') : '#660' ?>; font-weight: bold;">
									<?php echo $straperStatus['required'] ? ($straperStatus['installed'] ?'Installed':'Not Installed') : 'Already up-to-date'; ?>
								</span>
							</strong>
						</td>
					</tr>

					<?php if (count($status->modules)) : ?>
						<tr class="row1">
							<th>Module</th>
							<th>Client</th>
							<th></th>
						</tr>

						<?php foreach ($status->modules as $module) : ?>
							<tr class="row2 <?php //echo ($rows++ % 2); ?>">
								<td class="key"><?php echo ucfirst($module['name']); ?></td>
								<td class="key"><?php echo ucfirst($module['client']); ?></td>
								<td><strong style="color: <?php echo ($module['result'])? "green" : "red"?>"><?php echo ($this->componentStatus=="install") ?(($module['result'])?'Installed':'Not installed'):$updatemsg; ?></strong>

								<?php
								if ($this->componentStatus=="install")
								{
									if (!empty($module['result'])) // if installed then only show msg
									{
										echo $mstat=($module['status']? $enable :$disable);
									}
								}
								?>
								</td>
							</tr>
						<?php endforeach;?>
					<?php endif;?>

					<!-- pLUGIN DETAILS -->
					<?php if (count($status->plugins)) : ?>
						<tr class="row1">
							<th colspan="2">Plugin</th>
							<!--<th>Group</th>-->
							<th></th>
						</tr>

						<?php
						$oldplugingroup = "";
						foreach ($status->plugins as $plugin) :
							if ($oldplugingroup!=$plugin['group'])
							{
								$oldplugingroup=$plugin['group'];
								?>
								<tr class="row0">
									<th colspan="2"><strong><?php echo ucfirst($oldplugingroup)." Plugins";?></strong></th>
									<th></th>
									<!--<td></td>-->
								</tr>
								<?php
							}
							?>
							<tr class="row2 <?php //echo ($rows++ % 2); ?>">
								<td colspan="2" class="key"><?php echo ucfirst($plugin['name']); ?></td>
								<!--<td class="key"><?php //echo ucfirst($plugin['group']); ?></td> -->
								<td>
									<strong style="color: <?php echo ($plugin['result'])? "green" : "red"?>"><?php echo ($this->componentStatus=="install") ?(($plugin['result'])?'Installed':'Not installed'):$updatemsg; ?></strong>

									<?php
									if ($this->componentStatus=="install")
									{
										if (!empty($plugin['result']))
										{
										echo $pstat=($plugin['status']? "<span class=\"label label-success\">Enabled</span>" : "<span class=\"label label-important\">Disabled</span>");

										}
									}
									?>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
		<!-- end akeeba bootstrap -->
		<?php
	}

	/**
	 * Installs subextensions (modules, plugins) bundled with the main extension
	 *
	 * @param JInstaller $parent
	 * @return JObject The subextension installation status
	 */
	private function _installSubextensions($parent)
	{
		$src = $parent->getParent()->getPath('source');

		$db = JFactory::getDbo();

		$status = new JObject();
		$status->modules = array();
		$status->plugins = array();

		// Modules installation

		if (count($this->installation_queue['modules'])) {
			foreach($this->installation_queue['modules'] as $folder => $modules) {
				if (count($modules))
					foreach($modules as $module => $modulePreferences)
					{
						// Install the module
						if (empty($folder))
							$folder = 'site';
						$path = "$src/modules/$folder/$module";
						if (!is_dir($path))// if not dir
						{
							$path = "$src/modules/$folder/mod_$module";
						}
						if (!is_dir($path)) {
							$path = "$src/modules/$module";
						}

						if (!is_dir($path)) {
							$path = "$src/modules/mod_$module";
						}
						if (!is_dir($path))
						{

							$fortest='';
							//continue;
						}

						// Was the module already installed?
						$sql = $db->getQuery(true)
							->select('COUNT(*)')
							->from('#__modules')
							->where($db->qn('module').' = '.$db->q('mod_'.$module));
						$db->setQuery($sql);

						$count = $db->loadResult();
						$installer = new JInstaller;
						$result = $installer->install($path);
						$status->modules[] = array(
							'name'=>$module,
							'client'=>$folder,
							'result'=>$result,
							'status'=>$modulePreferences[1]
						);
						// Modify where it's published and its published state
						if (!$count) {
							// A. Position and state
							list($modulePosition, $modulePublished) = $modulePreferences;
							if ($modulePosition == 'cpanel') {
								$modulePosition = 'icon';
							}
							$sql = $db->getQuery(true)
								->update($db->qn('#__modules'))
								->set($db->qn('position').' = '.$db->q($modulePosition))
								->where($db->qn('module').' = '.$db->q('mod_'.$module));
							if ($modulePublished) {
								$sql->set($db->qn('published').' = '.$db->q('1'));
							}
							$db->setQuery($sql);
							$db->query();

							// B. Change the ordering of back-end modules to 1 + max ordering
							if ($folder == 'admin') {
								$query = $db->getQuery(true);
								$query->select('MAX('.$db->qn('ordering').')')
									->from($db->qn('#__modules'))
									->where($db->qn('position').'='.$db->q($modulePosition));
								$db->setQuery($query);
								$position = $db->loadResult();
								$position++;

								$query = $db->getQuery(true);
								$query->update($db->qn('#__modules'))
									->set($db->qn('ordering').' = '.$db->q($position))
									->where($db->qn('module').' = '.$db->q('mod_'.$module));
								$db->setQuery($query);
								$db->query();
							}

							// C. Link to all pages
							$query = $db->getQuery(true);
							$query->select('id')->from($db->qn('#__modules'))
								->where($db->qn('module').' = '.$db->q('mod_'.$module));
							$db->setQuery($query);
							$moduleid = $db->loadResult();

							$query = $db->getQuery(true);
							$query->select('*')->from($db->qn('#__modules_menu'))
								->where($db->qn('moduleid').' = '.$db->q($moduleid));
							$db->setQuery($query);
							$assignments = $db->loadObjectList();
							$isAssigned = !empty($assignments);
							if (!$isAssigned) {
								$o = (object)array(
									'moduleid'	=> $moduleid,
									'menuid'	=> 0
								);
								$db->insertObject('#__modules_menu', $o);
							}
						}
					}
			}
		}

		// Plugins installation
		if (count($this->installation_queue['plugins'])) {
			foreach($this->installation_queue['plugins'] as $folder => $plugins) {
				if (count($plugins))
				foreach($plugins as $plugin => $published) {
					$path = "$src/plugins/$folder/$plugin";
					if (!is_dir($path)) {
						$path = "$src/plugins/$folder/plg_$plugin";
					}
					if (!is_dir($path)) {
						$path = "$src/plugins/$plugin";
					}
					if (!is_dir($path)) {
						$path = "$src/plugins/plg_$plugin";
					}
					if (!is_dir($path)) continue;

					// Was the plugin already installed?
					$query = $db->getQuery(true)
						->select('COUNT(*)')
						->from($db->qn('#__extensions'))
						->where('( '.($db->qn('name').' = '.$db->q($plugin)) .' OR '. ($db->qn('element').' = '.$db->q($plugin)) .' )')
						->where($db->qn('folder').' = '.$db->q($folder));
					$db->setQuery($query);
					$count = $db->loadResult();

					$installer = new JInstaller;
					$result = $installer->install($path);

					$status->plugins[] = array('name'=>$plugin,'group'=>$folder, 'result'=>$result,'status'=>$published);


					if ($published && !$count) {
						$query = $db->getQuery(true)
							->update($db->qn('#__extensions'))
							->set($db->qn('enabled').' = '.$db->q('1'))
							->where('( '.($db->qn('name').' = '.$db->q($plugin)) .' OR '. ($db->qn('element').' = '.$db->q($plugin)) .' )')
							->where($db->qn('folder').' = '.$db->q($folder));
						$db->setQuery($query);
						$db->query();
					}
				}
			}
		}

		// library installation
		if (count($this->installation_queue['libraries'])) {
			foreach($this->installation_queue['libraries']  as $folder=>$status1) {

					$path = "$src/libraries/$folder";

					$query = $db->getQuery(true)
						->select('COUNT(*)')
						->from($db->qn('#__extensions'))
						->where('( '.($db->qn('name').' = '.$db->q($folder)) .' OR '. ($db->qn('element').' = '.$db->q($folder)) .' )')
						->where($db->qn('folder').' = '.$db->q($folder));
					$db->setQuery($query);
					$count = $db->loadResult();

					$installer = new JInstaller;
					$result = $installer->install($path);

					$status->libraries[] = array('name'=>$folder,'group'=>$folder, 'result'=>$result,'status'=>$status1);

					if ($published && !$count) {
						$query = $db->getQuery(true)
							->update($db->qn('#__extensions'))
							->set($db->qn('enabled').' = '.$db->q('1'))
							->where('( '.($db->qn('name').' = '.$db->q($folder)) .' OR '. ($db->qn('element').' = '.$db->q($folder)) .' )')
							->where($db->qn('folder').' = '.$db->q($folder));
						$db->setQuery($query);
						$db->query();
					}
			}
		}

		return $status;
	}

	private function _installStraper($parent)
	{
		$src = $parent->getParent()->getPath('source');

		// Install the FOF framework
		jimport('joomla.filesystem.folder');
		jimport('joomla.filesystem.file');
		jimport('joomla.utilities.date');
		$source = $src.DS.'tj_strapper';
		$target = JPATH_ROOT.DS.'media'.DS.'techjoomla_strapper';

		$haveToInstallStraper = false;
		if (!JFolder::exists($target)) {
			$haveToInstallStraper = true;
		} else {
			$straperVersion = array();
			if (JFile::exists($target.DS.'version.txt')) {
				$rawData = JFile::read($target.DS.'version.txt');
				$info = explode("\n", $rawData);
				$straperVersion['installed'] = array(
					'version'	=> trim($info[0]),
					'date'		=> new JDate(trim($info[1]))
				);
			} else {
				$straperVersion['installed'] = array(
					'version'	=> '0.0',
					'date'		=> new JDate('2011-01-01')
				);
			}
			$rawData = JFile::read($source.DS.'version.txt');
			$info = explode("\n", $rawData);
			$straperVersion['package'] = array(
				'version'	=> trim($info[0]),
				'date'		=> new JDate(trim($info[1]))
			);

			$haveToInstallStraper = $straperVersion['package']['date']->toUNIX() > $straperVersion['installed']['date']->toUNIX();
		}

		$installedStraper = false;
		if ($haveToInstallStraper) {
			$versionSource = 'package';
			$installer = new JInstaller;
			$installedStraper = $installer->install($source);
		} else {
			$versionSource = 'installed';
		}

		if (!isset($straperVersion)) {
			$straperVersion = array();
			if (JFile::exists($target.DS.'version.txt')) {
				$rawData = JFile::read($target.DS.'version.txt');
				$info = explode("\n", $rawData);
				$straperVersion['installed'] = array(
					'version'	=> trim($info[0]),
					'date'		=> new JDate(trim($info[1]))
				);
			} else {
				$straperVersion['installed'] = array(
					'version'	=> '0.0',
					'date'		=> new JDate('2011-01-01')
				);
			}
			$rawData = JFile::read($source.DS.'version.txt');
			$info = explode("\n", $rawData);
			$straperVersion['package'] = array(
				'version'	=> trim($info[0]),
				'date'		=> new JDate(trim($info[1]))
			);
			$versionSource = 'installed';
		}

		if (!($straperVersion[$versionSource]['date'] instanceof JDate)) {
			$straperVersion[$versionSource]['date'] = new JDate();
		}

		return array(
			'required'	=> $haveToInstallStraper,
			'installed'	=> $installedStraper,
			'version'	=> $straperVersion[$versionSource]['version'],
			'date'		=> $straperVersion[$versionSource]['date']->format('Y-m-d'),
		);
	}
}

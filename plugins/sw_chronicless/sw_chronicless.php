<?php
/*------------------------------------------------------------------------
# WoW Armory Sync Plugin
# com_raidplanner - RaidPlanner Component
# ------------------------------------------------------------------------
# author    Taracque
# copyright Copyright (C) 2011 Taracque. All Rights Reserved.
# @license - http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
# Website: http://www.taracque.hu/raidplanner
-------------------------------------------------------------------------*/
// no direct access
defined( '_JEXEC' ) or die( 'Restricted access' );

class RaidPlannerPluginSw_chronicless extends RaidPlannerPlugin
{
	function __construct( $guild_id, $guild_name, $params)
	{
		parent::__construct( $guild_id, $guild_name, $params);
		
		$this->provide_sync = true;
	}

	public function doSync( $showOkStatus = false )
	{
		$db = & JFactory::getDBO();

		/* load database ids race array */
		$races = array();
		$query = "SELECT race_id, race_name FROM #__raidplanner_race WHERE race_name='Human'";
		$db->setQuery( $query );
		$races = $db->loadAssocList( 'race_name' );

		/* load database ids race array */
		$classes = array();
		$query = "SELECT class_id, class_name FROM #__raidplanner_class WHERE class_name='Player'";
		$db->setQuery( $query );
		$classes = $db->loadAssocList( 'class_name' );

		/* Load Cabal data */
		$url = "http://chronicless.einhyrning.com/cabal/";
		$url .= rawurlencode( $this->guild_name );
		$url .= ".json";

		$data = json_decode( $this->getData( $url ) );
		if (function_exists('json_last_error')) {
			if (json_last_error() != JSON_ERROR_NONE)
			{
				JError::raiseWarning('100','Chronicless data decoding error');
				return null;
			}
		}
		if (isset($data->status) && ($data->status=="fail"))
		{
			JError::raiseWarning('100','Chronicless failed' . $data->error->description);
			return null;
		}

		if (($this->guild_name == @$data->name) && ($data->name!=''))
		{
			$params = array(
				'governingForm'	=> $data->governingForm,
				'side'			=> $data->faction->name,
				'logo'			=> $data->faction->logo,
				'link'			=> $data->url,
				'char_link'		=> "http://chronicle.thesecretworld.com/character/%s",
			);

			$this->params = array_merge( $this->params, $params );
			
			$query = "UPDATE #__raidplanner_guild SET
							guild_name=".$db->Quote($data->name).",
							params=".$db->Quote(json_encode($params)).",
							lastSync=NOW()
							WHERE guild_id=".intval($this->guild_id);
			$db->setQuery($query);
			$db->query();

			/* detach characters from guild */
			$query = "UPDATE #__raidplanner_character SET guild_id=0 WHERE guild_id=".intval($this->guild_id)."";
			$db->setQuery($query);
			$db->query();

			foreach($data->members as $member)
			{
				// check if character exists
				$query = "SELECT character_id FROM #__raidplanner_character WHERE char_name LIKE BINARY ".$db->Quote($member->name)."";
				$db->setQuery($query);
				$char_id = $db->loadResult();
				// not found insert it
				if (!$char_id) {
					$query="INSERT INTO #__raidplanner_character SET char_name=".$db->Quote($member->name)."";
					$db->setQuery($query);
					$db->query();
					$char_id=$db->insertid();
				}
				$query = "UPDATE #__raidplanner_character SET class_id='" . $classes[ 'Player' ][ 'class_id' ] . "'
															,race_id='" . $races[ 'Human' ][ 'race_id' ] . "'
															,gender_id='" . (($member->gender=='Male')?1:2) . "'
															,char_level='" . intval($member->rank) . "'
															,guild_id='" . intval($this->guild_id) . "'
															WHERE character_id=" . $char_id;
				$db->setQuery($query);
				$db->query();
			}

			/* delete all guildless characters */
			$query = "DELETE FROM #__raidplanner_character WHERE guild_id=0";
			$db->setQuery($query);
			$db->query();
			
			if ($showOkStatus)
			{
				JError::raiseNotice('0', 'Chronicless Sync successed');
			}
		} else {
			JError::raiseWarning('100', 'Chronicless data doesn\'t match');
		}
	}

	public function characterLink( $char_name )
	{
		/* Middle name needed */
		$names = explode(" ", $char_name);
		return sprintf($this->params['char_link'], rawurlencode($names[1]) ) . '" target="_blank';
	}
	
	public function guildHeader()
	{
		$header = array();
		$header[] = '<div>';
		$header[] = '<img src="' . $this->params['logo']  . '" alt="' . $this->guild_name . '" align="left" />';
		$header[] = '<h2><a href="' . $this->params['link'] . '" target="_blank">' . $this->guild_name . '</a></h2>';
		$header[] = '<strong>' . $this->params['side'] . " - " . $this->params['governingForm'] . '</strong>';
		$header[] = '</div>';

		return implode("\n", $header);
	}

	public function loadCSS()
	{
		return true;
	}

}
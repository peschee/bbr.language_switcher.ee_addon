<?php
/**
* biber Ltd. Language Switcher
*
* This file must be placed in the
* /system/extensions/ folder in your ExpressionEngine installation.
*
* @package bbrLanguageSwitch
* @version 1.1.1
* @author Can Berkol <http://biberltd.com>
* @see http://biberltd.com
* @copyright Copyright (C) 2009, biber Ltd.
* @license {@link http://creativecommons.org/licenses/by-sa/3.0/ Creative Commons Attribution-Share Alike 3.0 Unported} All source code commenting and attribution must not be removed. This is a condition of the attribution clause of the license.
*/

if ( ! defined('EXT')) exit('Invalid file request');

if ( ! defined('BBR_LS_version')){

	define("BBR_LS_version",			"1.1.2");
	define("BBR_LS_docs_url",			"http://biberltd.com/");
	define("BBR_LS_addon_id",			"BBR Lang Switcher");
	define("BBR_LS_extension_class",	"Bbr_langswitch");
	define("BBR_LS_cache_name",			"bbr_cache");
	define("BBR_LS_default_settings",	"german,de|english,en");
	define("BBR_LS_default_language",	"english,en");
}
/**
* This extension does work on the back-end (server-side) only. It is used in aid with several hacks that are documented on ExpressionEngine forums.
* The extension enables the server to dynamically set the userinterface language both for guests and logged-in users.
*
* @package bbrLanguageSwitch
* @version 1.1.0
* @author Can Berkol <http://biberltd.com>
* @see http://biberltd.com
* @copyright Copyright (C) 2009 biber Ltd.
* @license {@link http://creativecommons.org/licenses/by-sa/3.0/ Creative Commons Attribution-Share Alike 3.0 Unported} All source code commenting and attribution must not be removed. This is a condition of the attribution clause of the license.
*/
class Bbr_langswitch
{

	var $settings			= array();
	var $name				= 'biber Language Switcher';
	var $version			= BBR_LS_version;
	var $description		= 'Changes the user language based on the path.php settings.';
	var $settings_exist		= 'y';
	var $docs_url			= BBR_LS_docs_url;

	/**
	* PHP4 Constructor
	*
	* @see __construct()
	*/
	function Bbr_langswitch($settings = array('available_languages' => BBR_LS_default_settings, 'default_language' => BBR_LS_default_language))
	{
		$this->__construct($settings);
	}
	/**
	* PHP 5 Constructor
	*
	* @param	array|string $settings Extension settings associative array or an empty string
	* @since	Version 1.1.0
	*/
	function __construct($settings = array('available_languages' => BBR_LS_default_settings, 'default_language' => BBR_LS_default_language))
	{
		$this->settings = $settings;
	}

	/**
	* Activates the extension
	*
	* @return	bool Always TRUE
	* @since	Version 1.0.0
	*/
	function activate_extension()
	{
		global $DB;

		// Delete old hooks
		$DB->query("DELETE FROM exp_extensions WHERE class = '". $this->class_name ."'");

		$hooks = array(
			'sessions_start'		=> 'extend_language_class',
			'language_fetch_start'	=> 'change_lang',
		);

		foreach ($hooks as $hook => $method)
		{
			$sql[] = $DB->insert_string( 'exp_extensions',
										array('extension_id'	=> '',
												'class'			=> get_class($this),
												'method'		=> $method,
												'hook'			=> $hook,
												'settings'		=> serialize($this->settings),
												'priority'		=> 1,
												'version'		=> $this->version,
												'enabled'		=> "y"
											)
										);
		}

		// run all sql queries
		foreach ($sql as $query)
		{
			$DB->query($query);
		}

		return TRUE;
	}
	/**
	* Updates the extension
	*
	* @param	string $current If installed the current version of the extension otherwise an empty string
	* @return	bool FALSE if the extension is not installed or is the current version
	* @since	Version 1.0.0
	*/
	function update_extension($current = '')
	{
		global $DB;

		if ($current == '' OR $current == $this->version)
		{
			return FALSE;
		}

		$sql[] = "UPDATE `exp_extensions` SET `version` = '" . $DB->escape_str($this->version) . "' WHERE `class` = '" . get_class($this) . "'";

		// run all sql queries
		foreach ($sql as $query)
		{
			$DB->query($query);
		}
		return TRUE;
	}
	/**
	* Disables the extension the extension and deletes settings from DB
	*
	* @since	Version 1.0.0
	*/
	function disable_extension()
	{
		global $DB;
		$DB->query("DELETE FROM exp_extensions WHERE class = '" . get_class($this) . "'");
		return TRUE;
	}
	/**
	 *	For members (logged-in users) it sets the user language both in Database and in $SESS object.
	 *	For guests it create two cookies language, and language_code and sets the values of those cookies.
	 *
	 *	Then based on the language variables the function sets the $IN->global_vars['language'] and
	 *	$IN->global_vars['language_code'].
	 *
	 *	This way when users navigate to http://www.yoursite.com/language_code/ the user will see the site in specified language.
	 *	If user navigates to http://www.yoursite.com/ then the user will either see the site based on its previous settings
	 *	(either from cookies or DB). If user settings are not set then the system's default language will be called.
	 *
	 * @since	Version 1.1.1
	 */
	function change_lang()
	{
		global $IN, $DB, $SESS, $FNS, $PREFS;

		/**
		 * Based on user defined settings we extract default and available languages and language codes
		 * for later use.
		 */
		$usr_def_language = explode(',', $this->settings['default_language']);
		$site_languages_temp = explode('|', $this->settings['available_languages']);
		$usr_site_languages = array();

		foreach ($site_languages_temp as $language)
		{
			$site_language_details = explode(',', $language);
			$usr_site_languages[] = $site_language_details;
		}
		/**
		 *	Extra validation, just in case if something is wrong with any of the global objects.
		 */
		if (!isset($IN, $DB, $SESS, $FNS, $PREFS))
		{
			return FALSE;
		}
		/**
		 *	Check if set_language global variable is set. If it is set (and it is set to no) then
		 *	get the previous language files. Otherwise fetch new language files.
		 */
		if (!isset($IN->global_vars['set_language']))
		{

			if (isset($IN->global_vars['language'])){
				$glob_lang = $IN->global_vars['language'];
			}
			else
			{
				$glob_lang = (empty($SESS->userdata['language'])) ? $PREFS->ini('deft_lang') : $SESS->userdata['language'];
			}

			if (isset($IN->global_vars['language_code'])){
				$glob_lang_code = $IN->global_vars['language_code'];
			}
			else
			{
				$glob_lang_code = $usr_def_language[1];
			}
			/**
			 *	is the requested info within the array?
			 */
			if (isset($SESS->userdata['member_id'])){
				$member_id = $SESS->userdata['member_id'];
			}
			else {
				$member_id = 0;
			}
			/**
			 *	if this is a logged-in user then get the language stored in session, set language in DB.
			 */
			if (is_numeric($member_id) && $member_id > 0)
			{

				$curr_lang = $SESS->userdata['language'];
				$deft_lang = $PREFS->ini('deft_lang');

				if (empty($curr_lang))
				{
					$curr_lang = (!empty($deft_lang)) ? $deft_lang : 'english';
				}

				if (is_numeric($member_id) && $member_id > 0)
				{
					/**
					 * Change language only if the current language differs from the selected language
					 */
					if ($glob_lang != $curr_lang){
						$DB->query("UPDATE exp_members SET language = '$glob_lang' WHERE member_id = '$member_id'");
						$IN->global_vars['language'] = $glob_lang;
						$IN->global_vars['language_code'] = $glob_lang_code;
						$SESS->userdata['language'] = $glob_lang;
						$SESS->userdata['language_code'] = $glob_lang_code;
					}
				}
			}
			else 
			{
				/**
				 *	if this is an anonymous (guest) user then get the language stored in cookie
				 */
				$cook_lang = $IN->GBL('language', 'COOKIE');
				$deft_lang = $PREFS->ini('deft_lang');

				if (empty($deft_lang))
				{
					$deft_lang = $usr_def_language[0];
				}

				if (isset($IN->global_vars['language_code']))
				{
					$glob_lang_code = $IN->global_vars['language_code'];
				}
				else
				{
					$glob_lang_code = $usr_def_language[1];
				}
				$curr_lang = (empty($cook_lang)) ? $PREFS->ini('deft_lang') : $cook_lang;
				$glob_lang = (isset($IN->global_vars['language'])) ? $IN->global_vars['language'] : $deft_lang;
				/**
				 * Change language only if the current language differs from the selected language
				 */
				if ($glob_lang != $curr_lang)
				{
					$SESS->userdata['language'] = $glob_lang;
					$SESS->userdata['language_code'] = $glob_lang_code;
					$IN->global_vars['language'] = $glob_lang;
					$IN->global_vars['language_code'] = $glob_lang_code;
					$FNS->set_cookie('language', $glob_lang, 60*60*24*3);
					$FNS->set_cookie('language_code', $glob_lang_code, 60*60*24*3);
				}
			}
		}
		else
		{
			/**
			 * Get system defaults for language files.
			 */
			$deft_lang = $PREFS->ini('deft_lang');
			if (empty($deft_lang) || is_null($deft_lang))
			{
				$deft_lang = $usr_def_language[0];
			}
			$deft_lang_code = $this->switch_langcode($deft_lang, $usr_def_language, $usr_site_languages);
			$member_id = (isset($SESS->userdata['member_id'])) ? $SESS->userdata['member_id'] : 0;
			/**
			 *	if member id is greater than 0 then do the necessary processing for logged in user otherwise do necessar proccessing for guests.
			 */
			if ($member_id > 0)
			{
				/**
				 *	Read language related session data if it exists. Otherwise set user language to --.
				 */
				$user_lang = (!empty($SESS->userdata['language'])) ? $SESS->userdata['language'] : '--';
				$user_lang_code = $this->switch_langcode($user_lang, $usr_def_language, $usr_site_languages);
				if ($user_lang == '--' || empty($user_lang))
				{
					$user_lang = $deft_lang;
				}
				$glob_lang = $user_lang;
				$glob_lang_code = $user_lang_code;
				$DB->query("UPDATE exp_members SET language = '$glob_lang' WHERE member_id = '$member_id'");
			}
			else
			{
				/**
				 *	Read language related date that is stored in cookie if the cookie exists. Otherwise set cookie language values to --.
				 */
				$cook_lang = $IN->GBL('language', 'COOKIE');
				if (empty($cook_lang) || is_null($cook_lang))
				{
					$cook_lang = '--';
				}

				$cook_lang_code = $IN->GBL('language_code', 'COOKIE');
				if (empty($cook_lang_code) || is_null($cook_lang_code))
				{
					$cook_lang_code = '--';
				}

				if (($cook_lang == '--' || empty($cook_lang)) || ($cook_lang_code == '--' || empty($cook_lang_code)))
				{
					$cook_lang = $deft_lang;
					$cook_lang_code = $deft_lang_code;
				}

				$glob_lang = $cook_lang;
				$glob_lang_code = $cook_lang_code;
				$FNS->set_cookie('language', $glob_lang, 60*60*24*3);
				$FNS->set_cookie('language_code', $glob_lang_code, 60*60*24*3);
			}
			$SESS->userdata['language'] = $glob_lang;
			$SESS->userdata['language_code'] = $glob_lang_code;
			$IN->global_vars['language'] = $glob_lang;
			$IN->global_vars['language_code'] = $glob_lang_code;
		}
		return FALSE;
	}
  /**
	* Switches through user defined languages. It accepts three paramtes. First is the language name that system is switching to. Second is an array that holds user defined default site language details. Third is an array of user defined site wide used language details.
	*
	* @since	Version 1.1.0
	*/
	function switch_langcode($language, $usr_def_language, $usr_site_languages)
	{

		if (!in_array($language, $usr_site_languages))
		{
			$code = $usr_def_language[1];
		}
		else
		{
			foreach ($usr_site_languages as $language_details)
			{
				if ($language_details[0] == $language)
				{
					$code = $language_details[1];
					break;
				}
			}
		}
		return $code;
	}

	/**
	* Extends the default EE Language class in order to add the new hook
	* without using any core hacks.
	*
	* @author	Peter Siska, Designchuchi
	* @since	Version 1.1.2
	*/
	function extend_language_class()
	{
		global $LANG;
		$LANG = new Custom_Language_Class;
	}

	/**
	* Creates settings with default values.
	*
	* @since	Version 1.1.0
	*/
	function settings()
	{
		$settings = array();

		$settings['available_languages'] = BBR_LS_default_settings;
		$settings['default_language']	 = BBR_LS_default_language;

		return $settings;
	}
}

/**
* Extend the Language Class
*
* Thanks to Brian Litzinger and Nathan Pitman of (Nine Four) http://ninefour.co.uk/labs.
* Changed the original extension version using a custom language class as proposed by Brian
* here http://expressionengine.com/forums/viewthread/120522/
*/
class System_Messages_Output extends Language
{
	/** -------------------------------------
	/**	 Fetch a language file
	/** -------------------------------------*/
	function fetch_language_file($which = '', $package = '')
	{
		global $IN, $OUT, $LANG, $SESS, $PREFS, $FNS, $DB, $EXT;

		/* -------------------------------------------
		 * 'language_fetch_start' hook.
		 *	- Do all you want before language fetch routine
		 *	- Proposed by Can Berkol - biber Ltd.
		 */
		if ($EXT->active_hook('language_fetch_start') === TRUE)
		{
			$edata = $EXT->call_extension('language_fetch_start');
			if ($EXT->end_script === TRUE) return;
		}
		/*
		/* -------------------------------------------*/

		if ($which == '')
		{
			return;
		}

		if ($SESS->userdata['language'] != '')
		{
			$user_lang = $SESS->userdata['language'];
		}
		else
		{
			if ($IN->GBL('language', 'COOKIE'))
			{
				$user_lang = $IN->GBL('language', 'COOKIE');
			}
			elseif ($PREFS->ini('deft_lang') != '')
			{
				$user_lang = $PREFS->ini('deft_lang');
			}
			else
			{
				$user_lang = 'english';
			}
		}

		// Sec.ur.ity code.	 ::sigh::
		$which = str_replace(array('lang.', EXT), '', $which);
		$package = ($package == '') ? $FNS->filename_security($which) : $FNS->filename_security($package);
		$user_lang = $FNS->filename_security($user_lang);

		if ($which == 'sites_cp')
		{
			$phrase = 'base'.'6'.'4_d'.'ecode';
			eval($phrase(preg_replace("|\s+|is", '', "Z2xvYmFsICREQiwgJERTUDsgJEVFX1NpdGVzID0gbmV3IEVFX1NpdGVzKCk7CiRzdHJpbmcgPSBiYXNlNjRfZGVjb2RlKCRFRV9TaXRlcy0+dGh
			lX3NpdGVzX2FsbG93ZWQuJEVFX1NpdGVzLT5udW1fc2l0ZXNfYWxsb3dlZC4kRUVfU2l0ZXMtPnNpdGVzX2FsbG93ZWRfbnVtKTsKJGhhc2ggPSBtZDUoIk1TTSBCeSBFbGxpc0xhYiIpOwoJCmZvciAo
			JGkgPSAwLCAkc3RyID0gIiI7ICRpIDwgc3RybGVuKCRzdHJpbmcpOyAkaSsrKQp7Cgkkc3RyIC49IHN1YnN0cigkc3RyaW5nLCAkaSwgMSkgXiBzdWJzdHIoJGhhc2gsICgkaSAlIHN0cmxlbigkaGFza
			CkpLCAxKTsKfQoKJHN0cmluZyA9ICRzdHI7Cgpmb3IgKCRpID0gMCwgJGRlYyA9ICIiOyAkaSA8IHN0cmxlbigkc3RyaW5nKTsgJGkrKykKewoJJGRlYyAuPSAoc3Vic3RyKCRzdHJpbmcsICRpKyssID
			EpIF4gc3Vic3RyKCRzdHJpbmcsICRpLCAxKSk7Cn0KCiRhbGxvd2VkID0gc3Vic3RyKGJhc2U2NF9kZWNvZGUoc3Vic3RyKGJhc2U2NF9kZWNvZGUoc3Vic3RyKGJhc2U2NF9kZWNvZGUoc3Vic3RyKCR
			kZWMsMikpLDUpKSw0KSksMik7CgokcXVlcnkgPSAkREItPnF1ZXJ5KCJTRUxFQ1QgQ09VTlQoKikgQVMgY291bnQgRlJPTSBleHBfc2l0ZXMiKTsKCmlmICggISBpc19udW1lcmljKCRhbGxvd2VkKSBP
			UiAkcXVlcnktPnJvd1siY291bnQiXSA+PSAkYWxsb3dlZCkKewoJJHRoaXMtPmxhbmd1YWdlWyJjcmVhdGVfbmV3X3NpdGUiXSA9ICIiOwoJCglpZiAoaXNzZXQoJF9HRVRbIlAiXSkgJiYgaW5fYXJyY
			XkoJF9HRVRbIlAiXSwgYXJyYXkoIm5ld19zaXRlIiwgInVwZGF0ZV9zaXRlIikpICYmIGVtcHR5KCRfUE9TVFsic2l0ZV9pZCJdKSkKCXsKCQlkaWUoIk11bHRpcGxlIFNpdGUgTWFuYWdlciBFcnJvci
			AtIFNpdGUgTGltaXQgUmVhY2hlZCIpOwoJfQp9"))); return;
		}

		if ( ! in_array($which, $this->cur_used))
		{
			if ($user_lang != 'english')
			{
				$paths = array(
								PATH_MOD.$package.'/language/'.$user_lang.'/lang.'.$which.EXT,
								PATH_MOD.$package.'/language/english/lang.'.$which.EXT,
								PATH_LANG.$user_lang.'/lang.'.$which.EXT,
								PATH_LANG.'english/lang.'.$which.EXT
							);
			}
			else
			{
				$paths = array(
								PATH_MOD.$package.'/language/english/lang.'.$which.EXT,
								PATH_LANG.'english/lang.'.$which.EXT
							);
			}

			$success = FALSE;

			foreach($paths as $path)
			{
				if (file_exists($path) && @include $path)
				{
					$success = TRUE;
					break;
				}
			}

			if ($success !== TRUE)
			{
				if ($PREFS->ini('debug') >= 1)
				{
					$error = 'Unable to load the following language file:<br /><br />lang.'.$which.EXT;
					return $OUT->fatal_error($error);
				}
				else
				{
					return;
				}
			}

			$this->cur_used[] = $which;

			if (isset($L))
			{
				$this->language = array_merge($this->language, $L);
				unset($L);
			}
		}
	}
	/* END */
}
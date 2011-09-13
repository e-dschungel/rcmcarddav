<?php
/*
    RCM CardDAV Plugin
    Copyright (C) 2011 Benjamin Schieder <blindcoder@scavenger.homeip.net>

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License along
    with this program; if not, write to the Free Software Foundation, Inc.,
    51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 */
$rcmail = rcmail::get_instance();
$user = $rcmail->user;;
require_once(dirname(__FILE__) . '/carddav_backend.php');

class carddav extends rcube_plugin
{
  public $task = 'addressbook|mail|settings';
  private $abook_id = "CardDAV";

  public function init()
  {{{
    $this->add_hook('addressbooks_list', array($this, 'address_sources'));
    $this->add_hook('addressbook_get', array($this, 'get_address_book'));

    $this->add_hook('preferences_list', array($this, 'cd_preferences'));
    $this->add_hook('preferences_save', array($this, 'cd_save'));
    $this->add_hook('preferences_sections_list',array($this, 'cd_preferences_section'));

    // use this address book for autocompletion queries
    // (maybe this should be configurable by the user?)
    $config = rcmail::get_instance()->config;
    $sources = (array) $config->get('autocomplete_addressbooks', array('sql'));
    if (!in_array($this->abook_id, $sources)) {
      $sources[] = $this->abook_id;
      $config->set('autocomplete_addressbooks', $sources);
    }
  }}}

  public function address_sources($p)
  {{{
    $abook = new carddav_backend;
    $rcmail = rcmail::get_instance();
    $prefs = $rcmail->config->get('carddav', array());
    if ($prefs['use_carddav'])
      $p['sources'][$this->abook_id] = array(
        'id' => $this->abook_id,
        'name' => 'CardDAV',
        'readonly' => $abook->readonly,
        'groups' => $abook->groups,
      );
    return $p;
  }}}

  public function get_address_book($p)
  {{{
    if ($p['id'] === $this->abook_id) {
      $p['instance'] = new carddav_backend;
    }

    return $p;
  }}}

  // user preferences
  function cd_preferences($args)
  {{{
	if($args['section'] != 'cd_preferences')
		return;
	$this->add_texts('localization/', false);
	$rcmail = rcmail::get_instance();
	
	if (version_compare(PHP_VERSION, '5.3.0') < 0) {
		$args['blocks']['cd_preferences'] = array(
			'options' => array(
				array('title'=> Q($this->gettext('cd_php_too_old')), 'content' => PHP_VERSION)
			),
			'name' => Q($this->gettext('cd_title'))
		);
		return $args;
	}

	$prefs = $rcmail->config->get('carddav', array());
	
	$use_carddav = $prefs['use_carddav'];
	$username = $prefs['username'];
	$password = $prefs['password'];
	$url = $prefs['url'];
	$lax_resource_checking = $prefs['lax_resource_checking'];

	if (file_exists("plugins/carddav/config.inc.php")){
		require("plugins/carddav/config.inc.php");
	}

	$dont_override = $rcmail->config->get('dont_override', array());

	if (in_array('carddav_use_carddav', $dont_override)) {
		$content_use_carddav = $use_carddav ? "Enabled" : "Disabled";
	} else {
		// check box for activating
		$checkbox = new html_checkbox(array('name' => '_cd_use_carddav', 'value' => 1));
		$content_use_carddav = $checkbox->show($use_carddav?1:0);
	}
	if (in_array('carddav_username', $dont_override)){
		$content_username = $username;
	} else {
		// input box for username
		$input = new html_inputfield(array('name' => '_cd_username', 'type' => 'text', 'autocomplete' => 'off', 'value' => $username));
		$content_username = $input->show();
	}
	if (in_array('carddav_password', $dont_override)){
		$content_password = "***";
	} else {
		// input box for password
		$input = new html_inputfield(array('name' => '_cd_password', 'type' => 'password', 'autocomplete' => 'off', 'value' => $password));
		$content_password = $input->show();
	}
	if (in_array('carddav_url', $dont_override)){
		$content_url = str_replace("%u", "$username", $url);
	} else {
		// input box for URL
		$size = isset($prefs['url']) ? strlen($url) : 40;
		$input = new html_inputfield(array('name' => '_cd_url', 'type' => 'text', 'autocomplete' => 'off', 'value' => $prefs['url'], 'size' => $size < 40 ? 40 : $size));
		$content_url = $input->show();
	}
	if (in_array('carddav_lax_resource_checking', $dont_override)){
		$content_lax_resource_checking = $lax_resource_checking ? "Enabled" : "Disabled";
	} else {
		// input box for lax resource checking
		$checkbox = new html_checkbox(array('name' => '_cd_lax_resource_checking', 'value' => 1));
		$content_lax_resource_checking = $checkbox->show($lax_resource_checking?1:0);
	}

	$args['blocks']['cd_preferences'] = array(
		'options' => array(
			array('title'=> Q($this->gettext('cd_use_carddav')), 'content' => $content_use_carddav), 
			array('title'=> Q($this->gettext('cd_username')), 'content' => $content_username), 
			array('title'=> Q($this->gettext('cd_password')), 'content' => $content_password),
			array('title'=> Q($this->gettext('cd_url')), 'content' => $content_url),
			array('title'=> Q($this->gettext('cd_lax_resource_checking')), 'content' => $content_lax_resource_checking),
		),
		'name' => Q($this->gettext('cd_title'))
	);
	return($args);
  }}}

  // add a section to the preferences tab
  function cd_preferences_section($args)
  {{{
	$this->add_texts('localization/', false);
	$args['list']['cd_preferences'] = array(
		'id'      => 'cd_preferences',
		'section' => Q($this->gettext('cd_title'))
	);
	return($args);
  }}}

  // save preferences
  function cd_save($args)
  {{{
	if($args['section'] != 'cd_preferences')
		return;

	$rcmail = rcmail::get_instance();

	$prefs = array(
		'use_carddav' => isset($_POST['_cd_use_carddav']) ? 1 : 0,
		'username' => get_input_value('_cd_username', RCUBE_INPUT_POST),
		'password' => get_input_value('_cd_password', RCUBE_INPUT_POST),
		'url' => get_input_value('_cd_url', RCUBE_INPUT_POST),
		'lax_resource_checking' => isset($_POST['_cd_lax_resource_checking']) ? 1 : 0
	);
	$args['prefs']['carddav'] = $prefs;
	return($args);
  }}}
}

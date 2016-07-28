<?php



	if (!defined('QA_VERSION')) { // don't allow this page to be requested directly from browser
			header('Location: ../../');
			exit;
	}
	

	qa_register_plugin_module('module', 'qa-filtertags-admin.php', 'qa_filtertags_admin', 'Filter Tags Admin');
	
	qa_register_plugin_overrides('qa-filtertags-overrides.php', 'Filter Tags Override');
/*
	Omit PHP closing tag to help avoid accidental output
*/

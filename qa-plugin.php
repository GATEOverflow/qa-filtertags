<?php



	if (!defined('QA_VERSION')) { // don't allow this page to be requested directly from browser
			header('Location: ../../');
			exit;
	}
	

	qa_register_plugin_module('module', 'qa-filtertags-admin.php', 'qa_filtertags_admin', 'Filter Tags Admin');
	
	qa_register_plugin_overrides('qa-filtertags-overrides.php', 'Filter Tags Override');

	qa_register_plugin_module('page', 'qa-filtertags-manage-page.php', 'qa_filtertags_manage_page', 'Filter Tags Manage Page');
/*
	Omit PHP closing tag to help avoid accidental output
*/

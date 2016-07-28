<?php



	if (!defined('QA_VERSION')) { // don't allow this page to be requested directly from browser
			header('Location: ../../');
			exit;
	}
	
	qa_register_plugin_layer('qa-filtertags-layer.php', 'Filter Tags Layer');	
	


	qa_register_plugin_module('module', 'qa-filtertags-admin.php', 'qa_filtertags_admin', 'Filter Tags Admin');

/*
	Omit PHP closing tag to help avoid accidental output
*/

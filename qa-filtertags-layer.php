<?php

class qa_html_theme_layer extends qa_html_theme_base {

	function head_custom()
	{
		qa_html_theme_base::head_custom();
		$this->output('<style type="text/css">'.qa_opt('topsearch_plugin_css').'</style>');
	}



}
?>

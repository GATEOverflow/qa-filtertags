<?php
class qa_filtertags_admin {

	function allow_template($template)
	{
		return ($template!='admin');
	}

	function option_default($option) {

		switch($option) {
			case 'qa-filtertags-global':
				return '';
			default:
				return null;

		}
	}
	function admin_form(&$qa_content)
	{

		//	Process form input

		$ok = null;
		if (qa_clicked('filtertags_save_button')) {
			foreach($_POST as $i => $v) {

				qa_opt($i,$v);
				
			}

			$ok = qa_lang('admin/options_saved');
		}
		else if (qa_clicked('filtertags_reset_button')) {
			foreach($_POST as $i => $v) {
				$def = $this->option_default($i);
				if($def !== null) qa_opt($i,$def);
			}
			$ok = qa_lang('admin/options_reset');
		}			
		//	Create the form for display


		$fields = array();


		$fields[] = array(
				'label' => 'Filter Tags (separate by comma)',
				'tags' => 'NAME="qa-filtertags-global"',
				'value' => qa_opt('qa-filtertags-global'),
				'type' => 'text',
				'note' => 'Questions having these tags will be globally hidden for all users'
				);


		return array(
				'ok' => ($ok && !isset($error)) ? $ok : null,

				'fields' => $fields,

				'buttons' => array(
					array(
						'label' => qa_lang_html('main/save_button'),
						'tags' => 'NAME="filtertags_save_button"',
					     ),
					array(
						'label' => qa_lang_html('admin/reset_options_button'),
						'tags' => 'NAME="filtertags_reset_button"',
					     ),
					),
			    );
	}


}

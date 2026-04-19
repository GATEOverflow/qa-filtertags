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
			// Track old tags before saving to detect newly added ones
			$oldTagString = qa_opt('qa-filtertags-global');
			$oldTags = array_filter(array_map('trim', explode(',', $oldTagString)), 'strlen');

			foreach($_POST as $i => $v) {
				qa_opt($i,$v);
			}

			// Detect new tags and assign owners
			$newTagString = qa_opt('qa-filtertags-global');
			$newTags = array_filter(array_map('trim', explode(',', $newTagString)), 'strlen');

			$ownersJson = qa_opt('qa-filtertags-owners');
			$owners = $ownersJson ? json_decode($ownersJson, true) : array();
			if (!is_array($owners)) $owners = array();

			$currentUserId = qa_get_logged_in_userid();

			foreach ($newTags as $tag) {
				if (!isset($owners[$tag])) {
					$owners[$tag] = $currentUserId ? (int)$currentUserId : 1;
				}
			}

			// Clean up owners for removed tags
			foreach (array_keys($owners) as $tag) {
				if (!in_array($tag, $newTags)) {
					unset($owners[$tag]);
				}
			}

			qa_opt('qa-filtertags-owners', json_encode($owners));

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

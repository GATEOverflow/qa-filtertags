<?php

if (!defined('QA_VERSION')) {
	header('Location: ../../');
	exit;
}

class qa_filtertags_flagged_page
{
	private $directory;
	private $urltoroot;

	public function load_module($directory, $urltoroot)
	{
		$this->directory = $directory;
		$this->urltoroot = $urltoroot;
	}

	public function suggest_requests()
	{
		return array(
			array(
				'title' => 'Flagged Posts by Tag',
				'request' => 'flagged',
				'nav' => null,
			),
		);
	}

	public function match_request($request)
	{
		return $request === 'flagged' || strpos($request, 'flagged/') === 0;
	}

	public function process_request($request)
	{
		require_once QA_INCLUDE_DIR . 'db/selects.php';
		require_once QA_INCLUDE_DIR . 'app/format.php';

		$currentUserId = qa_get_logged_in_userid();
		$currentLevel = qa_get_logged_in_level();
		$isAdmin = $currentLevel >= QA_USER_LEVEL_ADMIN;

		if (!$currentUserId) {
			$qa_content = qa_content_prepare();
			$qa_content['error'] = 'You must be logged in to view this page.';
			return $qa_content;
		}

		// Parse the request: flagged/tagname OR flagged/user/handle
		$parts = explode('/', $request);
		$mode = null; // 'tag' or 'user'
		$tag = null;
		$viewUserId = null;
		$viewUserHandle = null;

		if (count($parts) >= 3 && $parts[1] === 'user') {
			// flagged/user/handle
			$mode = 'user';
			$viewUserHandle = urldecode($parts[2]);
			$result = qa_db_query_sub("SELECT userid, handle FROM ^users WHERE handle = $", $viewUserHandle);
			$user = qa_db_read_one_assoc($result, true);
			if (!$user) {
				$qa_content = qa_content_prepare();
				$qa_content['error'] = 'User not found.';
				return $qa_content;
			}
			$viewUserId = (int)$user['userid'];
			$viewUserHandle = $user['handle'];

			// Permission: user can see own flagged content, admin can see anyone's
			if (!$isAdmin && (int)$currentUserId !== $viewUserId) {
				$qa_content = qa_content_prepare();
				$qa_content['error'] = 'Access denied. You can only view your own flagged content.';
				return $qa_content;
			}
		} elseif (count($parts) >= 2 && $parts[1] !== '') {
			// flagged/tagname
			$mode = 'tag';
			$tag = urldecode($parts[1]);

			// Permission: admin or tag owner
			$owners = $this->get_tag_owners();
			$tagOwner = isset($owners[$tag]) ? (int)$owners[$tag] : 0;

			if (!$isAdmin && (int)$currentUserId !== $tagOwner) {
				$qa_content = qa_content_prepare();
				$qa_content['error'] = 'Access denied. Only the tag owner or an admin can view this page.';
				return $qa_content;
			}

			// Verify tag exists in global list
			$globalTags = $this->get_global_tags();
			if (!in_array($tag, $globalTags)) {
				$qa_content = qa_content_prepare();
				$qa_content['error'] = 'Filter tag not found: ' . qa_html($tag);
				return $qa_content;
			}
		} else {
			// Just /flagged — show index of available tags with flagged counts (for admins/owners)
			return $this->render_index($isAdmin, $currentUserId);
		}

		// Get flagged posts using proper selectspec
		$start = qa_get_start();
		$pageSize = (int)qa_opt('page_size_qs');

		// Build selectspec based on the flagged selectspec
		$selectspec = qa_db_flagged_post_qs_selectspec($currentUserId, $start, true, $pageSize);

		// Add filter condition to the inner subquery
		if ($mode === 'tag') {
			$escapedTag = qa_db_escape_string($tag);
			$filterSql = " AND (CASE WHEN LEFT(type,1)='Q' THEN ^posts.postid ELSE ^posts.parentid END) IN ("
				. "SELECT pt.postid FROM ^posttags pt JOIN ^words w ON pt.wordid = w.wordid WHERE w.word = '" . $escapedTag . "'"
				. ")";
			$title = 'Flagged Posts in Tag: ' . qa_html($tag);
			$requestPath = 'flagged/' . urlencode($tag);
			$selectspec['source'] = str_replace(
				"WHERE flagcount>0 AND type IN ('Q', 'A', 'C')",
				"WHERE flagcount>0 AND type IN ('Q', 'A', 'C')" . $filterSql,
				$selectspec['source']
			);
			$total = $this->count_flagged_by_tag($tag);
			$filterParams = [];
		} else {
			$title = 'Posts by ' . qa_html($viewUserHandle) . ' — Flagged';
			$requestPath = 'flagged/user/' . urlencode($viewUserHandle);
			// Inject user filter before ORDER BY — avoids conflict with the selectspec override
			// which already patches the WHERE clause for GET filter params (filter_type/reason/public)
			$selectspec['source'] = str_replace(
				' ORDER BY ^posts.flagcount DESC',
				' AND ^posts.userid=' . (int)$viewUserId . ' ORDER BY ^posts.flagcount DESC',
				$selectspec['source']
			);
			$filterParams = [];
			if (qa_get('filter_public')) $filterParams['filter_public'] = '1';
			$ft = qa_get('filter_type');
			if ($ft && in_array($ft, ['Q', 'A', 'C'])) $filterParams['filter_type'] = $ft;
			$fr = qa_get('filter_reason');
			if ($fr) $filterParams['filter_reason'] = $fr;
			$total = $this->count_flagged_by_user($viewUserId, $filterParams);
		}

		$questions = qa_db_select_with_pending($selectspec);

		// Build content
		$qa_content = qa_content_prepare();
		$qa_content['title'] = $title;

		// Handle admin actions (clear flags / hide)
		if (qa_is_http_post()) {
			$this->handle_admin_actions($questions, $isAdmin);
			qa_redirect(qa_request());
		}

		$usershtml = qa_userids_handles_html(qa_any_get_userids_handles($questions));

		$qa_content['q_list'] = array(
			'form' => array(
				'tags' => 'method="post" action="' . qa_self_html() . '"',
				'hidden' => array(
					'code' => qa_get_form_security_code('admin/click'),
				),
			),
			'qs' => array(),
		);

		if (count($questions)) {
			foreach ($questions as $question) {
				$postid = qa_html(isset($question['opostid']) ? $question['opostid'] : $question['postid']);
				$elementid = 'p' . $postid;

				$htmloptions = qa_post_html_options($question);
				$htmloptions['voteview'] = false;
				$htmloptions['tagsview'] = (isset($question['obasetype']) ? $question['obasetype'] : $question['basetype']) == 'Q';
				$htmloptions['answersview'] = false;
				$htmloptions['viewsview'] = false;
				$htmloptions['contentview'] = true;
				$htmloptions['flagsview'] = true;
				$htmloptions['elementid'] = $elementid;

				$htmlfields = qa_any_to_q_html_fields($question, $currentUserId, qa_cookie_get(), $usershtml, null, $htmloptions);

				if (isset($htmlfields['what_url']))
					$htmlfields['url'] = $htmlfields['what_url'];

				// Only show action buttons if user has permission
				if ($isAdmin || !qa_user_permit_error('permit_hide_show')) {
					$htmlfields['form'] = array(
						'style' => 'light',
						'buttons' => array(
							'clearflags' => array(
								'tags' => 'name="admin_' . $postid . '_clearflags" onclick="return qa_admin_click(this);"',
								'label' => qa_lang_html('question/clear_flags_button'),
							),
							'hide' => array(
								'tags' => 'name="admin_' . $postid . '_hide" onclick="return qa_admin_click(this);"',
								'label' => qa_lang_html('question/hide_button'),
							),
						),
					);
				}

				$qa_content['q_list']['qs'][] = $htmlfields;
			}

			$qa_content['page_links'] = qa_html_page_links(
				$requestPath,
				$start,
				$pageSize,
				$total,
				2,
				$filterParams
			);
		} else {
			if ($mode === 'tag') {
				$qa_content['title'] = 'No flagged posts found in tag: ' . qa_html($tag);
			} else {
				$qa_content['title'] = 'No flagged posts by ' . qa_html($viewUserHandle);
			}
		}

		$qa_content['script_rel'][] = 'qa-content/qa-admin.js?' . QA_VERSION;

		return $qa_content;
	}

	private function render_index($isAdmin, $currentUserId)
	{
		$qa_content = qa_content_prepare();
		$qa_content['title'] = 'Flagged Posts Overview';

		$globalTags = $this->get_global_tags();
		$owners = $this->get_tag_owners();

		// Determine visible tags
		if ($isAdmin) {
			$visibleTags = $globalTags;
		} else {
			$visibleTags = array();
			foreach ($owners as $tag => $uid) {
				if ((int)$uid === (int)$currentUserId) {
					$visibleTags[] = $tag;
				}
			}
		}

		// Get flagged counts per tag in one batch query
		$tagCounts = $this->get_all_flagged_counts($visibleTags);

		// Also show link to own flagged content
		$ownCount = $this->count_flagged_by_user($currentUserId);

		$html = '';

		if ($ownCount > 0) {
			$html .= '<p><a href="' . qa_path_html('flagged/user/' . qa_get_logged_in_handle()) . '">'
				. 'Your flagged posts (' . $ownCount . ')</a></p>';
		}

		if (!empty($tagCounts)) {
			$html .= '<h3>Tags with Flagged Posts</h3>';
			$html .= '<table style="width:100%;border-collapse:collapse;">';
			$html .= '<thead><tr><th style="text-align:left;padding:8px 12px;border-bottom:2px solid #ddd;">Tag</th>'
				. '<th style="text-align:left;padding:8px 12px;border-bottom:2px solid #ddd;">Flagged Posts</th></tr></thead>';
			$html .= '<tbody>';
			foreach ($tagCounts as $tag => $count) {
				$html .= '<tr>';
				$html .= '<td style="padding:8px 12px;border-bottom:1px solid #eee;"><a href="' . qa_path_html('flagged/' . urlencode($tag)) . '">' . qa_html($tag) . '</a></td>';
				$html .= '<td style="padding:8px 12px;border-bottom:1px solid #eee;">' . $count . '</td>';
				$html .= '</tr>';
			}
			$html .= '</tbody></table>';
		} elseif (empty($tagCounts) && $ownCount === 0) {
			$qa_content['title'] = 'No flagged posts found';
		}

		$qa_content['custom'] = $html;
		return $qa_content;
	}

	private function handle_admin_actions($questions, $isAdmin)
	{
		require_once QA_INCLUDE_DIR . 'app/posts.php';

		if (!qa_check_form_security_code('admin/click', qa_post_text('code')))
			return;

		foreach ($questions as $question) {
			$postid = isset($question['opostid']) ? $question['opostid'] : $question['postid'];

			if (qa_post_text('admin_' . $postid . '_clearflags')) {
				qa_post_clear_flags($postid);
			} elseif (qa_post_text('admin_' . $postid . '_hide')) {
				qa_post_set_status($postid, QA_POST_STATUS_HIDDEN);
			}
		}
	}

	private function count_flagged_by_tag($tag)
	{
		$result = qa_db_query_sub(
			"SELECT COUNT(*) FROM ^posts p "
			. "WHERE p.flagcount > 0 AND p.type IN ('Q', 'A', 'C') "
			. "AND (CASE WHEN LEFT(p.type,1)='Q' THEN p.postid ELSE p.parentid END) IN ("
			. "  SELECT pt.postid FROM ^posttags pt JOIN ^words w ON pt.wordid = w.wordid WHERE w.word = $"
			. ")",
			$tag
		);
		return (int)qa_db_read_one_value($result, true);
	}

	private function count_flagged_by_user($userid, $filterParams = [])
	{
		$where = "flagcount > 0 AND type IN ('Q', 'A', 'C') AND userid = " . (int)$userid;
		if (!empty($filterParams['filter_type']) && in_array($filterParams['filter_type'], ['Q', 'A', 'C'])) {
			$where .= " AND LEFT(type,1)='" . $filterParams['filter_type'] . "'";
		}
		if (!empty($filterParams['filter_reason'])) {
			if ($filterParams['filter_reason'] === 'none') {
				$where .= " AND postid NOT IN (SELECT postid FROM ^flagreasons)";
			} elseif (is_numeric($filterParams['filter_reason']) && (int)$filterParams['filter_reason'] >= 1 && (int)$filterParams['filter_reason'] <= 7) {
				$where .= " AND postid IN (SELECT postid FROM ^flagreasons WHERE reasonid=" . (int)$filterParams['filter_reason'] . ")";
			}
		}
		if (!empty($filterParams['filter_public'])) {
			$globalTags = qa_opt('qa-filtertags-global');
			if (!empty($globalTags)) {
				$tags = array_map('trim', explode(',', $globalTags));
				$escaped = implode("','", array_map('qa_db_escape_string', $tags));
				$where .= " AND (CASE WHEN LEFT(type,1)='Q' THEN postid ELSE parentid END) NOT IN ("
					. "SELECT pt.postid FROM ^posttags pt JOIN ^words wd ON pt.wordid=wd.wordid WHERE wd.word IN ('" . $escaped . "')"
					. ")";
			}
		}
		$result = qa_db_query_sub("SELECT COUNT(*) FROM ^posts WHERE " . $where);
		return (int)qa_db_read_one_value($result, true);
	}

	private function get_global_tags()
	{
		$tagString = qa_opt('qa-filtertags-global');
		if (empty(trim($tagString))) return array();
		return array_values(array_filter(array_map('trim', explode(',', $tagString)), 'strlen'));
	}

	private function get_tag_owners()
	{
		$result = qa_db_query_sub(
			"SELECT tag, content FROM ^tagmetas WHERE title = 'filtertag_owner'"
		);
		$rows = qa_db_read_all_assoc($result);
		$owners = array();
		foreach ($rows as $row) {
			$owners[$row['tag']] = (int)$row['content'];
		}
		return $owners;
	}

	private function get_all_flagged_counts($tags)
	{
		if (empty($tags)) return array();
		$placeholders = implode(',', array_fill(0, count($tags), '$'));
		$result = qa_db_query_sub(
			"SELECT w.word, COUNT(DISTINCT p.postid) as cnt "
			. "FROM ^posts p "
			. "JOIN ^posttags pt ON (CASE WHEN LEFT(p.type,1)='Q' THEN p.postid ELSE p.parentid END) = pt.postid "
			. "JOIN ^words w ON pt.wordid = w.wordid "
			. "WHERE p.flagcount > 0 AND p.type IN ('Q', 'A', 'C') "
			. "AND w.word IN ($placeholders) "
			. "GROUP BY w.word",
			...$tags
		);
		$rows = qa_db_read_all_assoc($result);
		$counts = array();
		foreach ($rows as $row) {
			$counts[$row['word']] = (int)$row['cnt'];
		}
		return $counts;
	}
}

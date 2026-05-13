<?php

if (!defined('QA_VERSION')) {
	header('Location: ../../');
	exit;
}

class qa_filtertags_manage_page
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
				'title' => 'View Filter Tags Global',
				'request' => 'view-filtertags-global',
				'nav' => null,
			),
		);
	}

	public function match_request($request)
	{
		return $request === 'view-filtertags-global' || strpos($request, 'view-filtertags-global/') === 0;
	}

	public function process_request($request)
	{
		// Handle AJAX user search
		if (qa_is_http_post() && qa_post_text('ftm_user_search') !== null) {
			$this->ajax_user_search(qa_post_text('ftm_user_search'));
			return null;
		}

		$currentUserId = qa_get_logged_in_userid();
		$isAdmin = qa_get_logged_in_level() >= QA_USER_LEVEL_ADMIN;

		// Ensure all tags have owners (defaults to userid 1 for older tags)
		$owners = $this->ensure_owners_initialized();

		// Allow admins and tag owners
		$isOwner = $this->is_filtertag_owner($currentUserId);
		if (!$isAdmin && !$isOwner) {
			$qa_content = qa_content_prepare();
			$qa_content['error'] = 'Access denied. Admin or tag owner only.';
			return $qa_content;
		}

		$qa_content = qa_content_prepare();
		$qa_content['title'] = 'Manage Filter Tags';

		// Handle POST actions
		$message = $this->handle_post_actions($isAdmin, $currentUserId);

		// Re-read owners after potential changes
		$owners = $this->get_tag_owners();

		// Get all filter tags
		$globalTags = $this->get_global_tags();

		// Determine visible tags based on role
		$visibleTags = $isAdmin ? $globalTags : $this->get_owned_tags($currentUserId);

		// Extract tag from path: view-filtertags-global/tagname
		$selectedTag = null;
		if (strpos($request, 'view-filtertags-global/') === 0) {
			$selectedTag = urldecode(substr($request, strlen('view-filtertags-global/')));
		}

		// Check permission for selected tag
		if ($selectedTag && !in_array($selectedTag, $visibleTags)) {
			$qa_content = qa_content_prepare();
			$qa_content['error'] = 'Access denied. You do not own this tag.';
			return $qa_content;
		}

		// Build the page
		$html = '';

		if ($message) {
			$msgClass = (strpos($message, 'Error') !== false || strpos($message, 'mismatch') !== false || strpos($message, 'No user') !== false || strpos($message, 'Invalid') !== false || strpos($message, 'required') !== false || strpos($message, 'denied') !== false)
				? 'ftm-msg-error'
				: 'ftm-msg-success';
			$html .= '<div class="ftm-msg ' . $msgClass . '">' . qa_html($message) . '</div>';
		}

		// Styles
		$html .= '<style>
			:root {
				--ftm-bg: #fff;
				--ftm-text: #333;
				--ftm-text-muted: #666;
				--ftm-border: #ddd;
				--ftm-border-light: #eee;
				--ftm-th-bg: #f8f9fa;
				--ftm-hover-bg: #f5f5f5;
				--ftm-selected-bg: #e8f4fd;
				--ftm-input-bg: #fff;
				--ftm-input-border: #ccc;
				--ftm-btn-bg: #fff;
				--ftm-btn-hover-bg: #f0f0f0;
				--ftm-msg-success-bg: #d4edda;
				--ftm-msg-success-border: #c3e6cb;
				--ftm-msg-success-text: #155724;
				--ftm-msg-error-bg: #f8d7da;
				--ftm-msg-error-border: #f5c6cb;
				--ftm-msg-error-text: #721c24;
				--ftm-ac-bg: #fff;
			}
			html:not([data-theme="light"]) {
					--ftm-bg: #1e1e1e;
					--ftm-text: #ddd;
					--ftm-text-muted: #aaa;
					--ftm-border: #444;
					--ftm-border-light: #333;
					--ftm-th-bg: #2a2a2a;
					--ftm-hover-bg: #2c2c2c;
					--ftm-selected-bg: #1a3a4a;
					--ftm-input-bg: #2a2a2a;
					--ftm-input-border: #555;
					--ftm-btn-bg: #2a2a2a;
					--ftm-btn-hover-bg: #3a3a3a;
					--ftm-msg-success-bg: #1a3a2a;
					--ftm-msg-success-border: #2a5a3a;
					--ftm-msg-success-text: #8fd4a4;
					--ftm-msg-error-bg: #3a1a1a;
					--ftm-msg-error-border: #5a2a2a;
					--ftm-msg-error-text: #f4a4a4;
					--ftm-ac-bg: #2a2a2a;
			}
			.ftm-msg { padding:12px 16px; margin:10px 0 20px; border:1px solid; border-radius:4px; }
			.ftm-msg-success { background:var(--ftm-msg-success-bg); border-color:var(--ftm-msg-success-border); color:var(--ftm-msg-success-text); }
			.ftm-msg-error { background:var(--ftm-msg-error-bg); border-color:var(--ftm-msg-error-border); color:var(--ftm-msg-error-text); }
			.ftm-table { width:100%; border-collapse:collapse; margin-bottom:20px; color:var(--ftm-text); }
			.ftm-table th { text-align:left; padding:10px 12px; border-bottom:2px solid var(--ftm-border); background:var(--ftm-th-bg); font-weight:600; color:var(--ftm-text); }
			.ftm-table td { padding:10px 12px; border-bottom:1px solid var(--ftm-border-light); }
			.ftm-table tr:hover { background:var(--ftm-hover-bg); }
			.ftm-table tr.ftm-selected { background:var(--ftm-selected-bg); }
			.ftm-btn { padding:6px 14px; cursor:pointer; border:1px solid var(--ftm-input-border); border-radius:3px; background:var(--ftm-btn-bg); color:var(--ftm-text); }
			.ftm-btn:hover { background:var(--ftm-btn-hover-bg); }
			.ftm-btn-primary { background:#337ab7; color:#fff; border-color:#2e6da4; }
			.ftm-btn-primary:hover { background:#286090; }
			.ftm-btn-danger { color:#d9534f; border-color:#d9534f; }
			.ftm-btn-danger:hover { background:#d9534f; color:#fff; }
			.ftm-input { padding:6px 10px; border:1px solid var(--ftm-input-border); border-radius:3px; background:var(--ftm-input-bg); color:var(--ftm-text); }
			.ftm-section { margin:25px 0 15px; padding-bottom:8px; border-bottom:1px solid var(--ftm-border-light); color:var(--ftm-text); }
			.ftm-flex { display:flex; gap:10px; align-items:center; flex-wrap:wrap; margin-bottom:15px; }
			.ftm-muted { color:var(--ftm-text-muted); }
			.ftm-ac-wrap { position:relative; display:inline-block; width:300px; }
			.ftm-ac-wrap input { width:100%; box-sizing:border-box; }
			.ftm-ac-results { position:absolute; top:100%; left:0; right:0; background:var(--ftm-ac-bg); border:1px solid var(--ftm-input-border); border-top:none; max-height:200px; overflow-y:auto; z-index:100; display:none; }
			.ftm-ac-item { padding:8px 10px; cursor:pointer; font-size:13px; border-bottom:1px solid var(--ftm-border-light); color:var(--ftm-text); }
			.ftm-ac-item:hover, .ftm-ac-item.ftm-ac-active { background:var(--ftm-selected-bg); }
			.ftm-ac-item small { color:var(--ftm-text-muted); }
		</style>';

		// Only show tag listing when no tag is selected
		if (!$selectedTag || !in_array($selectedTag, $globalTags)) {
			// Batch load all stats in 3 queries instead of per-tag
			$allQuestionCounts = $this->get_all_question_counts($visibleTags);
			$allUserCounts = $this->get_all_user_counts($visibleTags);
			$allExclusiveCounts = $this->get_all_exclusive_counts($visibleTags, $globalTags);
			$ownerHandles = $this->get_owner_handles($owners, $visibleTags);
			$allFlaggedCounts = $this->get_all_flagged_counts($visibleTags);

			$html .= '<h2>Filter Tags (' . count($visibleTags) . ')</h2>';
			$html .= '<div class="ftm-flex"><input type="text" id="ftm-tag-search" placeholder="Search tags..." class="ftm-input" style="width:300px;" oninput="ftmFilterTags()"></div>';
			$html .= '<table class="ftm-table" id="ftm-tag-table">';
			$html .= '<thead><tr><th>#</th><th>Tag</th><th>Questions</th><th>Exclusive</th><th>Flagged</th><th>Users</th><th>Owner</th><th style="text-align:center;">Actions</th></tr></thead>';
			$html .= '<tbody>';

			if (empty($visibleTags)) {
				$html .= '<tr><td colspan="8" style="padding:12px;">No filter tags available.</td></tr>';
			} else {
				$securityCode = qa_get_form_security_code('filtertags-manage');
				$idx = 0;
				foreach ($visibleTags as $tag) {
					$tag = trim($tag);
					if (empty($tag)) continue;
					$idx++;

					$ownerUserId = isset($owners[$tag]) ? (int)$owners[$tag] : 1;
					$ownerHandle = isset($ownerHandles[$ownerUserId]) ? $ownerHandles[$ownerUserId] : 'Unknown';
					$manageUrl = qa_path_html('view-filtertags-global/' . urlencode($tag));
					$questionCount = isset($allQuestionCounts[$tag]) ? $allQuestionCounts[$tag] : 0;
					$exclusiveCount = isset($allExclusiveCounts[$tag]) ? $allExclusiveCounts[$tag] : 0;
					$flaggedCount = isset($allFlaggedCounts[$tag]) ? $allFlaggedCounts[$tag] : 0;
					$userCount = isset($allUserCounts[$tag]) ? $allUserCounts[$tag] : 0;

					$html .= '<tr class="ftm-tag-row" data-tag="' . qa_html(strtolower($tag)) . '">';
					$html .= '<td>' . $idx . '</td>';
					$html .= '<td><a href="' . $manageUrl . '"><strong>' . qa_html($tag) . '</strong></a></td>';
					$html .= '<td>' . $questionCount . '</td>';
					$html .= '<td>' . $exclusiveCount . '</td>';
					$html .= '<td>' . ($flaggedCount > 0 ? '<a href="' . qa_path_html('flagged/' . urlencode($tag)) . '" style="color:#d9534f;font-weight:600;">' . $flaggedCount . '</a>' : '0') . '</td>';
					$html .= '<td>' . $userCount . '</td>';
					$html .= '<td><a href="' . qa_path_html('user/' . $ownerHandle) . '">' . qa_html($ownerHandle) . '</a></td>';
					$html .= '<td style="text-align:center;">';
					$html .= '<a href="' . $manageUrl . '" class="ftm-btn ftm-btn-primary" style="text-decoration:none;font-size:13px;">Manage</a> ';
					$html .= '<form method="post" action="' . qa_path_html('view-filtertags-global') . '" style="display:inline;" onsubmit="return confirm(\'Delete filter tag \\\'' . qa_html($tag) . '\\\'? This removes it from the global list and from all users access lists.\');">';
					$html .= '<input type="hidden" name="action" value="delete_tag">';
					$html .= '<input type="hidden" name="tag" value="' . qa_html($tag) . '">';
					$html .= '<input type="hidden" name="code" value="' . qa_html($securityCode) . '">';
					$html .= '<input type="submit" value="Delete" class="ftm-btn ftm-btn-danger" style="font-size:13px;">';
					$html .= '</form>';
					$html .= '</td>';
					$html .= '</tr>';
				}
			}

			$html .= '</tbody></table>';

			// Count empty tags
			$emptyCount = 0;
			foreach ($visibleTags as $t) {
				$t = trim($t);
				if (!empty($t) && empty($allQuestionCounts[$t])) $emptyCount++;
			}
			if ($emptyCount > 0) {
				$html .= '<form method="post" action="' . qa_path_html('view-filtertags-global') . '" style="margin-top:15px;" onsubmit="return confirm(\'Delete all ' . $emptyCount . ' tags with 0 questions? This also removes them from all users access lists.\');">';
				$html .= '<input type="hidden" name="action" value="delete_empty_tags">';
				$html .= '<input type="hidden" name="code" value="' . qa_html($securityCode) . '">';
				$html .= '<input type="submit" value="Delete all empty tags (' . $emptyCount . ')" class="ftm-btn ftm-btn-danger">';
				$html .= '</form>';
			}

			$html .= '<script>
				function ftmFilterTags() {
					var q = document.getElementById("ftm-tag-search").value.toLowerCase();
					var rows = document.querySelectorAll(".ftm-tag-row");
					for (var i = 0; i < rows.length; i++) {
						rows[i].style.display = rows[i].getAttribute("data-tag").indexOf(q) >= 0 ? "" : "none";
					}
				}
			</script>';
		}

		// Tag detail view
		if ($selectedTag && in_array($selectedTag, $globalTags)) {
			$html .= $this->render_tag_detail($selectedTag, $isAdmin);
		}

		$qa_content['custom'] = $html;
		return $qa_content;
	}

	private function handle_post_actions($isAdmin = false, $currentUserId = null)
	{
		if (!qa_is_http_post()) return null;

		$action = qa_post_text('action');
		$tag = qa_post_text('tag');

		if (!$action) return null;

		if (!qa_check_form_security_code('filtertags-manage', qa_post_text('code'))) {
			return 'Security code mismatch. Please try again.';
		}

		// Actions that don't require a specific tag
		if ($action === 'delete_empty_tags') {
			return $this->action_delete_empty_tags($isAdmin, $currentUserId);
		}

		if (!$tag) return null;

		// Permission check: admin can do all, owner can add/remove users and change owner
		if (!$isAdmin) {
			$owners = $this->get_tag_owners();
			$tagOwner = isset($owners[$tag]) ? (int)$owners[$tag] : 1;
			if ((int)$currentUserId !== $tagOwner) {
				return 'Access denied. You are not the owner of this tag.';
			}
			if (!in_array($action, array('add_user', 'remove_user', 'change_owner', 'delete_tag'))) {
				return 'Access denied. Only admins can perform this action.';
			}
		}

		switch ($action) {
			case 'add_user':
				return $this->action_add_user($tag, qa_post_text('email'));
			case 'remove_user':
				return $this->action_remove_user($tag, (int)qa_post_text('userid'));
			case 'rename_tag':
				return $this->action_rename_tag($tag, qa_post_text('new_tag_name'), qa_post_text('rename_option'));
			case 'remove_tag_all_users':
				return $this->action_remove_tag_all_users($tag);
			case 'change_owner':
				return $this->action_change_owner($tag, qa_post_text('owner_email'));
			case 'delete_tag':
				return $this->action_delete_tag($tag);
		}

		return null;
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

	private function set_tag_owner($tag, $userid)
	{
		qa_db_query_sub(
			"INSERT INTO ^tagmetas (tag, title, content) VALUES ($, 'filtertag_owner', $) ON DUPLICATE KEY UPDATE content = $",
			$tag, (string)(int)$userid, (string)(int)$userid
		);
	}

	private function remove_tag_owner($tag)
	{
		qa_db_query_sub(
			"DELETE FROM ^tagmetas WHERE tag = $ AND title = 'filtertag_owner'",
			$tag
		);
	}

	private function ensure_owners_initialized()
	{
		$globalTags = $this->get_global_tags();
		$owners = $this->get_tag_owners();

		foreach ($globalTags as $tag) {
			if (!isset($owners[$tag])) {
				$defaultOwner = (stripos($tag, 'goclasses') !== false) ? 181161 : 1;
				$this->set_tag_owner($tag, $defaultOwner);
				$owners[$tag] = $defaultOwner;
			}
		}

		return $owners;
	}

	private function is_filtertag_owner($userid)
	{
		if (!$userid) return false;
		$owners = $this->get_tag_owners();
		return in_array((int)$userid, array_map('intval', array_values($owners)));
	}

	private function get_owned_tags($userid)
	{
		$owners = $this->get_tag_owners();
		$tags = array();
		foreach ($owners as $tag => $uid) {
			if ((int)$uid === (int)$userid) {
				$tags[] = $tag;
			}
		}
		return $tags;
	}

	private function get_user_handle($userid)
	{
		$result = qa_db_query_sub("SELECT handle FROM ^users WHERE userid = #", $userid);
		$handle = qa_db_read_one_value($result, true);
		return $handle ? $handle : 'Unknown';
	}

	private function get_owner_handles($owners, $tags)
	{
		$userIds = array();
		foreach ($tags as $tag) {
			$tag = trim($tag);
			if (empty($tag)) continue;
			$uid = isset($owners[$tag]) ? (int)$owners[$tag] : 1;
			$userIds[$uid] = true;
		}
		if (empty($userIds)) return array();
		$ids = array_keys($userIds);
		$placeholders = implode(',', array_fill(0, count($ids), '#'));
		$result = qa_db_query_sub(
			"SELECT userid, handle FROM ^users WHERE userid IN ($placeholders)",
			...$ids
		);
		$rows = qa_db_read_all_assoc($result);
		$handles = array();
		foreach ($rows as $row) {
			$handles[(int)$row['userid']] = $row['handle'];
		}
		return $handles;
	}

	private function get_all_question_counts($tags)
	{
		if (empty($tags)) return array();
		$placeholders = implode(',', array_fill(0, count($tags), '$'));
		$result = qa_db_query_sub(
			"SELECT w.word, COUNT(DISTINCT pt.postid) as cnt FROM ^posttags pt " .
			"JOIN ^words w ON pt.wordid = w.wordid " .
			"WHERE w.word IN ($placeholders) GROUP BY w.word",
			...$tags
		);
		$rows = qa_db_read_all_assoc($result);
		$counts = array();
		foreach ($rows as $row) {
			$counts[$row['word']] = (int)$row['cnt'];
		}
		return $counts;
	}

	private function get_all_user_counts($tags)
	{
		if (empty($tags)) return array();
		// Single scan of usermetas, count per tag using PHP
		$result = qa_db_query_sub(
			"SELECT content FROM ^usermetas WHERE title = 'questionaccesstags'"
		);
		$rows = qa_db_read_all_assoc($result);
		$tagSet = array_flip($tags);
		$counts = array();
		foreach ($rows as $row) {
			$userTags = array_filter(array_map('trim', explode(',', $row['content'])), 'strlen');
			foreach ($userTags as $ut) {
				if (isset($tagSet[$ut])) {
					$counts[$ut] = isset($counts[$ut]) ? $counts[$ut] + 1 : 1;
				}
			}
		}
		return $counts;
	}

	private function get_all_exclusive_counts($tags, $allGlobalTags)
	{
		if (empty($tags)) return array();
		// Get all postids for all global filter tags in one query
		$placeholders = implode(',', array_fill(0, count($allGlobalTags), '$'));
		$result = qa_db_query_sub(
			"SELECT w.word, pt.postid FROM ^posttags pt " .
			"JOIN ^words w ON pt.wordid = w.wordid " .
			"WHERE w.word IN ($placeholders)",
			...$allGlobalTags
		);
		$rows = qa_db_read_all_assoc($result);

		// Build: postid => set of global tags it has
		$postTags = array();
		foreach ($rows as $row) {
			$postTags[$row['postid']][] = $row['word'];
		}

		// Count exclusive posts per tag
		$tagSet = array_flip($tags);
		$counts = array();
		foreach ($postTags as $postid => $ptags) {
			if (count($ptags) === 1 && isset($tagSet[$ptags[0]])) {
				$t = $ptags[0];
				$counts[$t] = isset($counts[$t]) ? $counts[$t] + 1 : 1;
			}
		}
		return $counts;
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

	private function get_users_for_tag($tag)
	{
		$result = qa_db_query_sub(
			"SELECT u.userid, u.handle, u.email, um.content AS accesstags " .
			"FROM ^usermetas um JOIN ^users u ON um.userid = u.userid " .
			"WHERE um.title = 'questionaccesstags' AND FIND_IN_SET($, um.content) > 0 " .
			"ORDER BY u.handle",
			$tag
		);
		return qa_db_read_all_assoc($result);
	}

	private function get_question_count($tag)
	{
		$result = qa_db_query_sub(
			"SELECT COUNT(DISTINCT pt.postid) FROM ^posttags pt JOIN ^words w ON pt.wordid = w.wordid WHERE w.word = $",
			$tag
		);
		return (int)qa_db_read_one_value($result, true);
	}

	private function render_tag_detail($tag, $isAdmin = false)
	{
		$owners = $this->get_tag_owners();
		$users = $this->get_users_for_tag($tag);
		$questionCount = $this->get_question_count($tag);
		$formAction = qa_path_html('view-filtertags-global/' . urlencode($tag));
		$securityCode = qa_get_form_security_code('filtertags-manage');

		$ownerUserId = isset($owners[$tag]) ? (int)$owners[$tag] : 1;
		$ownerHandle = $this->get_user_handle($ownerUserId);

		$html = '<p><a href="' . qa_path_html('view-filtertags-global') . '">&larr; Back to all tags</a></p>';
		$html .= '<h2>Tag Detail: <em>' . qa_html($tag) . '</em></h2>';
		$html .= '<p>Questions with this tag: <strong>' . (int)$questionCount . '</strong></p>';
		$html .= '<p>Tag Owner: <strong><a href="' . qa_path_html('user/' . $ownerHandle) . '">' . qa_html($ownerHandle) . '</a></strong> (User ID: ' . $ownerUserId . ')</p>';

		// Show flagged posts link if any exist
		$flaggedCount = $this->count_flagged_by_tag($tag);
		if ($flaggedCount > 0) {
			$html .= '<p><a href="' . qa_path_html('flagged/' . urlencode($tag)) . '" style="color:#d9534f;font-weight:600;">&#9873; Flagged posts in this tag (' . $flaggedCount . ')</a></p>';
		}

		// Change owner form
		$html .= '<h3 class="ftm-section">Change Tag Owner</h3>';
		$html .= '<p class="ftm-muted" style="font-size:13px;margin-bottom:10px;">Current owner: <strong>' . qa_html($ownerHandle) . '</strong>. Transfer ownership to another user.</p>';
		$html .= '<form method="post" action="' . $formAction . '" onsubmit="return confirm(\'Transfer ownership of tag \\\'' . qa_html($tag) . '\\\'?\');" id="ftm-change-owner-form">';
		$html .= '<input type="hidden" name="action" value="change_owner">';
		$html .= '<input type="hidden" name="tag" value="' . qa_html($tag) . '">';
		$html .= '<input type="hidden" name="code" value="' . qa_html($securityCode) . '">';
		$html .= '<input type="hidden" name="owner_email" id="ftm-owner-email" value="">';
		$html .= '<div class="ftm-flex">';
		$html .= '<div class="ftm-ac-wrap"><input type="text" id="ftm-owner-input" placeholder="Search by handle or email..." autocomplete="off" class="ftm-input"><div class="ftm-ac-results" id="ftm-owner-results"></div></div>';
		$html .= '<input type="submit" value="Transfer Ownership" class="ftm-btn ftm-btn-primary">';
		$html .= '</div>';
		$html .= '</form>';

		// Admin-only actions
		if ($isAdmin) {
			// Rename tag form
			$html .= '<h3 class="ftm-section">Rename Tag (Admin Only)</h3>';
			$html .= '<form method="post" action="' . $formAction . '" onsubmit="return confirm(\'Are you sure you want to rename this tag?\');">';
			$html .= '<input type="hidden" name="action" value="rename_tag">';
			$html .= '<input type="hidden" name="tag" value="' . qa_html($tag) . '">';
			$html .= '<input type="hidden" name="code" value="' . qa_html($securityCode) . '">';
			$html .= '<div class="ftm-flex">';
			$html .= '<input type="text" name="new_tag_name" placeholder="New tag name" required class="ftm-input" style="width:300px;">';
			$html .= '<select name="rename_option" class="ftm-input">';
		$html .= '<option value="remove_from_users">Remove from all users\' access lists</option>';
		$html .= '<option value="rename_in_users">Rename in all users\' access lists</option>';
			$html .= '</select>';
			$html .= '<input type="submit" value="Rename Tag" class="ftm-btn ftm-btn-primary">';
			$html .= '</div>';
			$html .= '<p class="ftm-muted" style="font-size:12px;margin-top:5px;">';
			$html .= '<strong>Rename in all users\' access lists</strong>: Updates the tag name in qa-filtertags-global and in every user\'s questionaccesstags.<br>';
			$html .= '<strong>Remove from all users\' access lists</strong>: Updates the tag name in qa-filtertags-global but removes the old tag from every user\'s questionaccesstags.';
			$html .= '</p>';
			$html .= '</form>';

			// Remove tag from all users
			$html .= '<h3 class="ftm-section">Remove Tag Access from All Users (Admin Only)</h3>';
			$html .= '<p class="ftm-muted" style="font-size:13px;margin-bottom:10px;">This removes the tag <strong>' . qa_html($tag) . '</strong> from all users\' questionaccesstags. The tag remains in the global filter list.</p>';
			$html .= '<form method="post" action="' . $formAction . '" onsubmit="return confirm(\'This will remove the tag from ALL users access lists. Are you sure?\');">';
			$html .= '<input type="hidden" name="action" value="remove_tag_all_users">';
			$html .= '<input type="hidden" name="tag" value="' . qa_html($tag) . '">';
			$html .= '<input type="hidden" name="code" value="' . qa_html($securityCode) . '">';
			$html .= '<input type="submit" value="Remove Tag From All Users" class="ftm-btn ftm-btn-danger">';
			$html .= '</form>';
		}

		// Users with access table
		$html .= '<h3 class="ftm-section">Users with Access (' . count($users) . ')</h3>';

		// Add user form
		$html .= '<form method="post" action="' . $formAction . '" id="ftm-add-user-form">';
		$html .= '<input type="hidden" name="action" value="add_user">';
		$html .= '<input type="hidden" name="tag" value="' . qa_html($tag) . '">';
		$html .= '<input type="hidden" name="code" value="' . qa_html($securityCode) . '">';
		$html .= '<input type="hidden" name="email" id="ftm-add-user-email" value="">';
		$html .= '<div class="ftm-flex">';
		$html .= '<div class="ftm-ac-wrap"><input type="text" id="ftm-add-user-input" placeholder="Search by handle or email..." autocomplete="off" class="ftm-input"><div class="ftm-ac-results" id="ftm-add-user-results"></div></div>';
		$html .= '<input type="submit" value="Add User" class="ftm-btn ftm-btn-primary">';
		$html .= '</div>';
		$html .= '</form>';

		$html .= '<table class="ftm-table">';
		$html .= '<thead><tr><th>User ID</th><th>Handle</th><th>Email</th><th style="text-align:center;">Action</th></tr></thead>';
		$html .= '<tbody>';

		if (empty($users)) {
			$html .= '<tr><td colspan="4" style="padding:12px;">No users have access to this tag.</td></tr>';
		} else {
			foreach ($users as $user) {
				$html .= '<tr>';
				$html .= '<td>' . (int)$user['userid'] . '</td>';
				$html .= '<td><a href="' . qa_path_html('user/' . $user['handle']) . '">' . qa_html($user['handle']) . '</a></td>';
				$html .= '<td>' . qa_html($user['email']) . '</td>';
				$html .= '<td style="text-align:center;">';
				$html .= '<form method="post" action="' . $formAction . '" style="display:inline;" onsubmit="return confirm(\'Remove access to tag \\\'' . qa_html($tag) . '\\\' for user ' . qa_html($user['handle']) . '?\');">';
				$html .= '<input type="hidden" name="action" value="remove_user">';
				$html .= '<input type="hidden" name="tag" value="' . qa_html($tag) . '">';
				$html .= '<input type="hidden" name="userid" value="' . (int)$user['userid'] . '">';
				$html .= '<input type="hidden" name="code" value="' . qa_html($securityCode) . '">';
				$html .= '<input type="submit" value="Remove" class="ftm-btn ftm-btn-danger">';
				$html .= '</form>';
				$html .= '</td>';
				$html .= '</tr>';
			}
		}

		$html .= '</tbody></table>';

		// Autocomplete JS
		$searchUrl = qa_path('view-filtertags-global');
		$html .= '<script>
		(function() {
			var searchUrl = ' . json_encode($searchUrl) . ';
			var debounceTimers = {};

			function setupAutocomplete(inputId, resultsId, hiddenId) {
				var input = document.getElementById(inputId);
				var results = document.getElementById(resultsId);
				var hidden = document.getElementById(hiddenId);
				if (!input || !results || !hidden) return;

				input.addEventListener("input", function() {
					var q = input.value.trim();
					hidden.value = "";
					if (q.length < 2) { results.style.display = "none"; return; }
					if (debounceTimers[inputId]) clearTimeout(debounceTimers[inputId]);
					debounceTimers[inputId] = setTimeout(function() {
						var xhr = new XMLHttpRequest();
						xhr.open("POST", searchUrl, true);
						xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
						xhr.onload = function() {
							if (xhr.status === 200) {
								try {
									var users = JSON.parse(xhr.responseText);
									results.innerHTML = "";
									if (users.length === 0) {
										results.innerHTML = "<div class=\"ftm-ac-item\">No users found</div>";
									} else {
										for (var i = 0; i < users.length; i++) {
											(function(u) {
												var div = document.createElement("div");
												div.className = "ftm-ac-item";
												div.innerHTML = "<strong>" + u.handle + "</strong> <small>" + u.email + "</small>";
												div.addEventListener("mousedown", function(e) {
													e.preventDefault();
													input.value = u.handle + " (" + u.email + ")";
													hidden.value = u.email;
													results.style.display = "none";
												});
												results.appendChild(div);
											})(users[i]);
										}
									}
									results.style.display = "block";
								} catch(e) {}
							}
						};
						xhr.send("ftm_user_search=" + encodeURIComponent(q));
					}, 250);
				});

				input.addEventListener("blur", function() {
					setTimeout(function() { results.style.display = "none"; }, 200);
				});

				input.addEventListener("focus", function() {
					if (results.children.length > 0 && input.value.trim().length >= 2) results.style.display = "block";
				});
			}

			setupAutocomplete("ftm-add-user-input", "ftm-add-user-results", "ftm-add-user-email");
			setupAutocomplete("ftm-owner-input", "ftm-owner-results", "ftm-owner-email");
		})();
		</script>';

		return $html;
	}

	private function ajax_user_search($query)
	{
		$query = trim($query);
		if (strlen($query) < 2) {
			echo json_encode(array());
			exit;
		}

		if (strpos($query, '@') !== false) {
			$result = qa_db_query_sub(
				"SELECT userid, handle, email FROM ^users WHERE email LIKE # ORDER BY handle LIMIT 10",
				'%' . $query . '%'
			);
		} else {
			$result = qa_db_query_sub(
				"SELECT userid, handle, email FROM ^users WHERE handle LIKE # ORDER BY handle LIMIT 10",
				'%' . $query . '%'
			);
		}

		$users = qa_db_read_all_assoc($result);
		$out = array();
		foreach ($users as $u) {
			$out[] = array('userid' => (int)$u['userid'], 'handle' => $u['handle'], 'email' => $u['email']);
		}

		header('Content-Type: application/json');
		echo json_encode($out);
		exit;
	}

	// --- Actions ---

	private function action_add_user($tag, $email)
	{
		$email = trim($email);
		if (empty($email)) return 'Error: Email is required.';

		$result = qa_db_query_sub("SELECT userid, handle FROM ^users WHERE email = $", $email);
		$user = qa_db_read_one_assoc($result, true);

		if (!$user) return 'Error: No user found with email: ' . $email;

		$userid = (int)$user['userid'];
		$accesstags = qa_db_usermeta_get($userid, 'questionaccesstags');
		$tagsArray = $accesstags ? array_filter(array_map('trim', explode(',', $accesstags)), 'strlen') : array();

		if (in_array($tag, $tagsArray)) {
			return 'User ' . $user['handle'] . ' already has access to tag: ' . $tag;
		}

		$tagsArray[] = $tag;
		qa_db_usermeta_set($userid, 'questionaccesstags', implode(',', $tagsArray));

		return 'Added user ' . $user['handle'] . ' (' . $email . ') to tag: ' . $tag;
	}

	private function action_remove_user($tag, $userid)
	{
		if ($userid <= 0) return 'Error: Invalid user ID.';

		$accesstags = qa_db_usermeta_get($userid, 'questionaccesstags');
		if (!$accesstags) return 'Error: User has no access tags.';

		$tagsArray = array_filter(array_map('trim', explode(',', $accesstags)), 'strlen');
		$tagsArray = array_values(array_diff($tagsArray, array($tag)));

		qa_db_usermeta_set($userid, 'questionaccesstags', implode(',', $tagsArray));

		return 'Removed tag "' . $tag . '" access for user ID ' . $userid . '.';
	}

	private function action_rename_tag($oldTag, $newTag, $option)
	{
		$newTag = trim($newTag);
		if (empty($newTag)) return 'Error: New tag name is required.';
		if ($newTag === $oldTag) return 'Error: New tag name is the same as the old one.';

		// Rename in global filter tags
		$globalTags = $this->get_global_tags();
		$index = array_search($oldTag, $globalTags);
		if ($index !== false) {
			$globalTags[$index] = $newTag;
			qa_opt('qa-filtertags-global', implode(',', $globalTags));
		}

		// Update owner mapping
		$owners = $this->get_tag_owners();
		if (isset($owners[$oldTag])) {
			$this->set_tag_owner($newTag, $owners[$oldTag]);
			$this->remove_tag_owner($oldTag);
		}

		// Handle users' access tags
		$users = $this->get_users_for_tag($oldTag);
		$count = 0;

		foreach ($users as $user) {
			$tagsArray = array_filter(array_map('trim', explode(',', $user['accesstags'])), 'strlen');
			$pos = array_search($oldTag, $tagsArray);

			if ($pos !== false) {
				if ($option === 'rename_in_users') {
					$tagsArray[$pos] = $newTag;
				} else {
					unset($tagsArray[$pos]);
					$tagsArray = array_values($tagsArray);
				}
				qa_db_usermeta_set($user['userid'], 'questionaccesstags', implode(',', $tagsArray));
				$count++;
			}
		}

		$actionDesc = ($option === 'rename_in_users') ? 'renamed in' : 'removed from';
		return 'Tag renamed from "' . $oldTag . '" to "' . $newTag . '". Tag ' . $actionDesc . ' ' . $count . ' user(s) access lists.';
	}

	private function action_remove_tag_all_users($tag)
	{
		$users = $this->get_users_for_tag($tag);
		$count = 0;

		foreach ($users as $user) {
			$tagsArray = array_filter(array_map('trim', explode(',', $user['accesstags'])), 'strlen');
			$tagsArray = array_values(array_diff($tagsArray, array($tag)));
			qa_db_usermeta_set($user['userid'], 'questionaccesstags', implode(',', $tagsArray));
			$count++;
		}

		return 'Removed tag "' . $tag . '" from ' . $count . ' user(s) access lists.';
	}

	private function action_change_owner($tag, $email)
	{
		$email = trim($email);
		if (empty($email)) return 'Error: Email is required.';

		$result = qa_db_query_sub("SELECT userid, handle FROM ^users WHERE email = $", $email);
		$user = qa_db_read_one_assoc($result, true);

		if (!$user) return 'Error: No user found with email: ' . $email;

		$owners = $this->get_tag_owners();
		$oldOwnerUserId = isset($owners[$tag]) ? (int)$owners[$tag] : null;
		$newOwnerUserId = (int)$user['userid'];

		if ($oldOwnerUserId === $newOwnerUserId) {
			return 'Error: This user is already the owner of this tag.';
		}

		$this->set_tag_owner($tag, $newOwnerUserId);

		// Fire Q2A event
		$cookieid = isset($_COOKIE['qa_session']) ? $_COOKIE['qa_session'] : null;
		qa_report_event('filtertag_owner_changed', qa_get_logged_in_userid(), qa_get_logged_in_handle(), $cookieid, array(
			'tag' => $tag,
			'old_owner_userid' => $oldOwnerUserId,
			'new_owner_userid' => $newOwnerUserId,
		));

		// Email the new owner
		$siteName = qa_opt('site_title');
		$newSubject = $siteName . ' - You are now the owner of filter tag "' . $tag . '"';
		$newBody = 'Hi ' . $user['handle'] . ",\n\n" .
			'Ownership of the filter tag "' . $tag . '" on ' . $siteName . ' has been transferred to you.' . "\n" .
			'You can manage this tag at: ' . qa_path('view-filtertags-global/' . urlencode($tag), null, qa_opt('site_url')) . "\n\n" .
			'Thanks';
		qa_send_notification($newOwnerUserId, $email, $user['handle'], $newSubject, $newBody, array());

		// Email the old owner
		if ($oldOwnerUserId) {
			$oldOwnerResult = qa_db_query_sub("SELECT handle, email FROM ^users WHERE userid = #", $oldOwnerUserId);
			$oldOwner = qa_db_read_one_assoc($oldOwnerResult, true);
			if ($oldOwner) {
				$oldSubject = $siteName . ' - Filter tag "' . $tag . '" ownership transferred';
				$oldBody = 'Hi ' . $oldOwner['handle'] . ",\n\n" .
					'Ownership of the filter tag "' . $tag . '" on ' . $siteName . ' has been transferred from you to ' . $user['handle'] . '.' . "\n\n" .
					'Thanks';
				qa_send_notification($oldOwnerUserId, $oldOwner['email'], $oldOwner['handle'], $oldSubject, $oldBody, array());
			}
		}

		return 'Ownership of tag "' . $tag . '" transferred to ' . $user['handle'] . ' (' . $email . ').';
	}

	private function action_delete_tag($tag)
	{
		// Remove from global filter tags list
		$globalTags = $this->get_global_tags();
		$globalTags = array_values(array_diff($globalTags, array($tag)));
		qa_opt('qa-filtertags-global', implode(',', $globalTags));

		// Remove from owners
		$this->remove_tag_owner($tag);

		// Remove from all users' accesstags
		$users = $this->get_users_for_tag($tag);
		$count = 0;
		foreach ($users as $user) {
			$tagsArray = array_filter(array_map('trim', explode(',', $user['accesstags'])), 'strlen');
			$tagsArray = array_values(array_diff($tagsArray, array($tag)));
			qa_db_usermeta_set($user['userid'], 'questionaccesstags', implode(',', $tagsArray));
			$count++;
		}

		return 'Deleted filter tag "' . $tag . '". Removed from global list and from ' . $count . ' user(s) access lists.';
	}

	private function action_delete_empty_tags($isAdmin = false, $currentUserId = null)
	{
		$globalTags = $this->get_global_tags();
		$questionCounts = $this->get_all_question_counts($globalTags);

		// Scope to owned tags for non-admins
		$candidateTags = $isAdmin ? $globalTags : $this->get_owned_tags($currentUserId);

		$emptyTags = array();
		foreach ($candidateTags as $tag) {
			if (empty($questionCounts[$tag])) {
				$emptyTags[] = $tag;
			}
		}

		if (empty($emptyTags)) {
			return 'No empty tags found.';
		}

		// Remove from global list
		$remaining = array_values(array_diff($globalTags, $emptyTags));
		qa_opt('qa-filtertags-global', implode(',', $remaining));

		// Remove owners
		foreach ($emptyTags as $tag) {
			$this->remove_tag_owner($tag);
		}

		// Remove from users' accesstags
		$result = qa_db_query_sub(
			"SELECT userid, content FROM ^usermetas WHERE title = 'questionaccesstags'"
		);
		$rows = qa_db_read_all_assoc($result);
		$emptySet = array_flip($emptyTags);
		$userCount = 0;

		foreach ($rows as $row) {
			$tagsArray = array_filter(array_map('trim', explode(',', $row['content'])), 'strlen');
			$filtered = array_values(array_diff($tagsArray, $emptyTags));
			if (count($filtered) < count($tagsArray)) {
				qa_db_usermeta_set($row['userid'], 'questionaccesstags', implode(',', $filtered));
				$userCount++;
			}
		}

		return 'Deleted ' . count($emptyTags) . ' empty tag(s). Updated ' . $userCount . ' user(s) access lists.';
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
}

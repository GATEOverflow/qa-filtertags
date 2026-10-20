<?php

if (!defined('QA_VERSION')) {
	header('Location: ../../');
	exit;
}

class qa_html_theme_layer extends qa_html_theme_base
{
	function nav($navtype, $level = null)
	{
		if ($navtype === 'sub')
			$this->add_flagged_sub_nav();

		qa_html_theme_base::nav($navtype, $level);
	}

	private function add_flagged_sub_nav()
	{
		if (!qa_get_logged_in_userid())
			return;

		$parts = explode('/', qa_request());
		$handle = null;
		$selected = false;

		if ($parts[0] === 'user' && isset($parts[1]) && $parts[1] !== '') {
			// profile, questions, answers, wall pages of a user
			$handle = urldecode($parts[1]);
		} elseif ($parts[0] === 'flagged' && isset($parts[1]) && $parts[1] === 'user') {
			// the flagged page itself
			$handle = (isset($parts[2]) && $parts[2] !== '') ? urldecode($parts[2]) : qa_get_logged_in_handle();
			$selected = true;
		}

		if ($handle === null)
			return;

		// Only the user themselves or admins can see this tab
		$isOwn = (strcasecmp($handle, qa_get_logged_in_handle()) === 0);
		if (!$isOwn && qa_get_logged_in_level() < QA_USER_LEVEL_ADMIN)
			return;

		if (isset($this->content['navigation']['sub']['flagged']))
			return;

		$this->content['navigation']['sub']['flagged'] = array(
			'label' => 'Flagged',
			'url' => qa_path_html('flagged/user/' . $handle),
			'selected' => $selected,
		);
	}

	private function get_flagged_post_users()
	{
		$result = qa_db_query_sub(
			"SELECT DISTINCT p.userid, u.handle FROM ^posts p JOIN ^users u ON p.userid = u.userid " .
			"WHERE p.flagcount > 0 AND p.type IN ('Q', 'A', 'C') AND p.userid IS NOT NULL ORDER BY u.handle"
		);
		return qa_db_read_all_assoc($result);
	}

	function main_parts($content)
	{
		// Add filter toolbar on admin/flagged and flagged/user/* pages
		$req = qa_request();
		$isAdminFlagged = ($req === 'admin/flagged');
		$isUserFlagged  = ((strpos($req, 'flagged/user/') === 0) || (strpos($req, 'flagged/user') === 0));

		if ($isAdminFlagged || $isUserFlagged) {
			$currentPublic = qa_get('filter_public');
			$currentType = qa_get('filter_type');
			$currentReason = qa_get('filter_reason');
			$currentExcludeUsers = qa_get('filter_exclude_users');
			$excludeUserIds = array();
			if ($currentExcludeUsers) {
				$excludeUserIds = array_values(array_filter(array_map('intval', explode(',', $currentExcludeUsers))));
			}

			// Build current params to preserve across filter changes
			$params = [];
			if ($currentPublic) $params['filter_public'] = '1';
			if ($currentType) $params['filter_type'] = $currentType;
			if ($currentReason) $params['filter_reason'] = $currentReason;
			if (!empty($excludeUserIds)) $params['filter_exclude_users'] = implode(',', $excludeUserIds);

			// Base path differs per page
			if ($isUserFlagged) {
				$parts = explode('/', $req);
				$basePath = 'flagged/user/' . ((isset($parts[2]) && $parts[2] !== '') ? $parts[2] : qa_get_logged_in_handle());
				//error_log("Base path: " . $basePath);
			} else {
				$basePath = 'admin/flagged';
			}

			$activeStyle = 'background:#1a237e;color:#fff;border-color:#1a237e;';
			$btnStyle = 'display:inline-block;padding:6px 12px;border:1px solid #ccc;border-radius:6px;text-decoration:none;font-size:.82em;font-weight:500;color:#333;cursor:pointer;position:relative;z-index:11;';

			$html  = '<style>';
			$html .= '[data-theme="dark"] .qa-flagged-filter-bar{background:#1e1e2e!important;border-color:#3a3a4a!important;}';
			$html .= '[data-theme="dark"] .qa-flagged-filter-bar .qffb-label{color:#8a8aaa!important;}';
			$html .= '[data-theme="dark"] .qa-flagged-filter-bar a.qffb-btn{color:#ccc!important;border-color:#4a4a5a!important;background:#2a2a3e;}';
			$html .= '[data-theme="dark"] .qa-flagged-filter-bar a.qffb-btn:hover{background:#2e2e42!important;}';
			$html .= '[data-theme="dark"] .qa-flagged-filter-bar a.qffb-btn.qffb-active{background:#1a237e!important;color:#fff!important;border-color:#3949ab!important;}';
			$html .= '</style>';

			$html .= '<div class="qa-flagged-filter-bar" style="margin:0 0 18px;padding:14px 18px;background:#f8f9fa;border:1px solid #e0e0e0;border-radius:8px;position:relative;z-index:10;">';
			$html .= '<div class="qffb-label" style="font-size:.82em;font-weight:600;color:#555;text-transform:uppercase;letter-spacing:.5px;margin-bottom:10px;">Filters</div>';

			// Row 1: Visibility filter
			$html .= '<div style="margin-bottom:10px;display:flex;gap:6px;align-items:center;flex-wrap:wrap;">';
			$html .= '<span class="qffb-label" style="font-size:.82em;color:#666;font-weight:500;min-width:70px;">Visibility:</span>';
			$p = $params; unset($p['filter_public']);
			$html .= '<a href="' . qa_path_html($basePath, $p) . '" class="qffb-btn' . (!$currentPublic ? ' qffb-active' : '') . '" style="' . $btnStyle . (!$currentPublic ? $activeStyle : '') . '">All</a>';
			$p['filter_public'] = '1';
			$html .= '<a href="' . qa_path_html($basePath, $p) . '" class="qffb-btn' . ($currentPublic ? ' qffb-active' : '') . '" style="' . $btnStyle . ($currentPublic ? $activeStyle : '') . '">Public Only (no filter tags)</a>';
			$html .= '</div>';

			// Row 2: Post type filter
			$html .= '<div style="margin-bottom:10px;display:flex;gap:6px;align-items:center;flex-wrap:wrap;">';
			$html .= '<span class="qffb-label" style="font-size:.82em;color:#666;font-weight:500;min-width:70px;">Type:</span>';
			$p = $params; unset($p['filter_type']);
			$html .= '<a href="' . qa_path_html($basePath, $p) . '" class="qffb-btn' . (!$currentType ? ' qffb-active' : '') . '" style="' . $btnStyle . (!$currentType ? $activeStyle : '') . '">All</a>';
			foreach (['Q' => 'Questions', 'A' => 'Answers', 'C' => 'Comments'] as $code => $label) {
				$p['filter_type'] = $code;
				$html .= '<a href="' . qa_path_html($basePath, $p) . '" class="qffb-btn' . ($currentType === $code ? ' qffb-active' : '') . '" style="' . $btnStyle . ($currentType === $code ? $activeStyle : '') . '">' . $label . '</a>';
			}
			$html .= '</div>';

			// Row 3: Flag reason filter
			$reasons = [
				1 => 'Quality', 2 => 'Spam', 3 => 'Rude',
				4 => 'Needs Edit', 5 => 'Duplicate', 6 => 'Copied', 7 => 'Prohibited'
			];
			$html .= '<div style="margin-bottom:10px;display:flex;gap:6px;align-items:center;flex-wrap:wrap;">';
			$html .= '<span class="qffb-label" style="font-size:.82em;color:#666;font-weight:500;min-width:70px;">Reason:</span>';
			$p = $params; unset($p['filter_reason']);
			$html .= '<a href="' . qa_path_html($basePath, $p) . '" class="qffb-btn' . (!$currentReason ? ' qffb-active' : '') . '" style="' . $btnStyle . (!$currentReason ? $activeStyle : '') . '">All</a>';
			foreach ($reasons as $rid => $rlabel) {
				$p['filter_reason'] = $rid;
				$html .= '<a href="' . qa_path_html($basePath, $p) . '" class="qffb-btn' . ((int)$currentReason === $rid ? ' qffb-active' : '') . '" style="' . $btnStyle . ((int)$currentReason === $rid ? $activeStyle : '') . '">' . $rlabel . '</a>';
			}
			$p['filter_reason'] = 'none';
			$html .= '<a href="' . qa_path_html($basePath, $p) . '" class="qffb-btn' . ($currentReason === 'none' ? ' qffb-active' : '') . '" style="' . $btnStyle . ($currentReason === 'none' ? $activeStyle : '') . '">No Reason</a>';
			$html .= '</div>';

			// Row 4: Exclude users (admin/flagged only) - selection persisted in the browser via localStorage
			if ($isAdminFlagged) {
				$flaggedUsers = $this->get_flagged_post_users();

				if (!empty($flaggedUsers)) {
					$html .= '<div>';
					$html .= '<div style="display:flex;gap:6px;align-items:center;flex-wrap:wrap;margin-bottom:8px;">';
					$html .= '<span class="qffb-label" style="font-size:.82em;color:#666;font-weight:500;min-width:70px;">Exclude Users:</span>';
					$html .= '<input type="text" id="qffb-user-search" placeholder="Search users..." style="padding:5px 10px;border:1px solid #ccc;border-radius:6px;font-size:.82em;width:180px;" oninput="qffbFilterUsers()">';
					$html .= '<a href="javascript:void(0)" onclick="qffbResetExcluded()" class="qffb-btn" style="' . $btnStyle . '">Reset</a>';
					$html .= '</div>';
					$html .= '<div id="qffb-user-list" style="max-height:140px;overflow-y:auto;border:1px solid #e0e0e0;border-radius:6px;padding:8px 12px;background:#fff;display:flex;flex-wrap:wrap;gap:4px 16px;">';
					foreach ($flaggedUsers as $flaggedUser) {
						$uid = (int)$flaggedUser['userid'];
						$handleHtml = qa_html($flaggedUser['handle']);
						$checked = in_array($uid, $excludeUserIds) ? ' checked' : '';
						$html .= '<label class="qffb-user-item" data-handle="' . strtolower($handleHtml) . '" style="font-size:.82em;display:flex;align-items:center;gap:4px;white-space:nowrap;">';
						$html .= '<input type="checkbox" class="qffb-user-cb" value="' . $uid . '"' . $checked . ' onchange="qffbApplyExcluded()"> ' . $handleHtml;
						$html .= '</label>';
					}
					$html .= '</div>';
					$html .= '</div>';

					$html .= '<script>';
					$html .= 'function qffbGetParams(){return new URLSearchParams(window.location.search);}';
					$html .= 'function qffbNavigate(p){p.delete("start");var qs=p.toString();window.location.href=window.location.pathname+(qs?("?"+qs):"");}';
					$html .= 'function qffbFilterUsers(){var q=document.getElementById("qffb-user-search").value.toLowerCase();document.querySelectorAll(".qffb-user-item").forEach(function(item){item.style.display=item.getAttribute("data-handle").indexOf(q)>=0?"":"none";});}';
					$html .= 'function qffbApplyExcluded(){var ids=[];document.querySelectorAll(".qffb-user-cb:checked").forEach(function(cb){ids.push(cb.value);});try{localStorage.setItem("qa_flagged_exclude_users",JSON.stringify(ids));}catch(e){}var p=qffbGetParams();if(ids.length)p.set("filter_exclude_users",ids.join(","));else p.delete("filter_exclude_users");qffbNavigate(p);}';
					$html .= 'function qffbResetExcluded(){try{localStorage.removeItem("qa_flagged_exclude_users");}catch(e){}var p=qffbGetParams();p.delete("filter_exclude_users");qffbNavigate(p);}';
					$html .= '(function(){try{var stored=localStorage.getItem("qa_flagged_exclude_users");var storedIds=stored?JSON.parse(stored):[];var p=qffbGetParams();var current=p.get("filter_exclude_users");var currentIds=current?current.split(","):[];var a=storedIds.slice().sort();var b=currentIds.slice().sort();if(JSON.stringify(a)!==JSON.stringify(b)){if(storedIds.length)p.set("filter_exclude_users",storedIds.join(","));else p.delete("filter_exclude_users");p.delete("start");window.location.replace(window.location.pathname+(p.toString()?("?"+p.toString()):""));}}catch(e){}})();';
					$html .= '</script>';
				}
			}

			$html .= '</div>';

			$this->output($html);

			// Fix pagination for admin/flagged only (uses cached count; user page computes its own)
			if ($isAdminFlagged && ($currentPublic || $currentType || $currentReason || !empty($excludeUserIds))) {
				$this->_flagged_filter_params = $params;
			}
		}

		qa_html_theme_base::main_parts($content);
	}

	function page_links()
	{
		// If on admin/flagged with filters, rebuild page_links with correct count and params
		if (qa_request() === 'admin/flagged' && isset($this->_flagged_filter_params) && !empty($this->_flagged_filter_params)) {
			$filterParams = $this->_flagged_filter_params;

			$where = "flagcount > 0 AND type IN ('Q', 'A', 'C')";

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

			if (!empty($filterParams['filter_type']) && in_array($filterParams['filter_type'], ['Q', 'A', 'C'])) {
				$where .= " AND LEFT(type,1)='" . $filterParams['filter_type'] . "'";
			}

			if (!empty($filterParams['filter_reason'])) {
				if ($filterParams['filter_reason'] === 'none') {
					$where .= " AND postid NOT IN (SELECT postid FROM ^flagreasons)";
				} elseif (is_numeric($filterParams['filter_reason'])) {
					$rid = (int)$filterParams['filter_reason'];
					if ($rid >= 1 && $rid <= 7) {
						$where .= " AND postid IN (SELECT postid FROM ^flagreasons WHERE reasonid=" . $rid . ")";
					}
				}
			}

			if (!empty($filterParams['filter_exclude_users'])) {
				$userIds = array_filter(array_map('intval', explode(',', $filterParams['filter_exclude_users'])));
				if (!empty($userIds)) {
					$where .= " AND userid NOT IN (" . implode(',', $userIds) . ")";
				}
			}

			$total = (int)qa_db_read_one_value(qa_db_query_sub("SELECT COUNT(*) FROM ^posts WHERE " . $where), true);
			$start = qa_get_start();
			$pageSize = (int)qa_opt('page_size_qs');

			$this->content['page_links'] = qa_html_page_links(
				qa_request(),
				$start,
				$pageSize,
				$total,
				2,
				$filterParams
			);
		}

		qa_html_theme_base::page_links();
	}
}

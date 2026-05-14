<?php

if (!defined('QA_VERSION')) {
	header('Location: ../../');
	exit;
}

class qa_html_theme_layer extends qa_html_theme_base
{
	function main_parts($content)
	{
		// Add filter toolbar on admin/flagged and flagged/user/* pages
		$req = qa_request();
		$isAdminFlagged = ($req === 'admin/flagged');
		$isUserFlagged  = (strpos($req, 'flagged/user/') === 0);

		if ($isAdminFlagged || $isUserFlagged) {
			$currentPublic = qa_get('filter_public');
			$currentType = qa_get('filter_type');
			$currentReason = qa_get('filter_reason');

			// Build current params to preserve across filter changes
			$params = [];
			if ($currentPublic) $params['filter_public'] = '1';
			if ($currentType) $params['filter_type'] = $currentType;
			if ($currentReason) $params['filter_reason'] = $currentReason;

			// Base path differs per page
			if ($isUserFlagged) {
				$parts = explode('/', $req);
				$basePath = 'flagged/user/' . (isset($parts[2]) ? $parts[2] : '');
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
			$html .= '<div style="display:flex;gap:6px;align-items:center;flex-wrap:wrap;">';
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

			$html .= '</div>';

			$this->output($html);

			// Fix pagination for admin/flagged only (uses cached count; user page computes its own)
			if ($isAdminFlagged && ($currentPublic || $currentType || $currentReason)) {
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

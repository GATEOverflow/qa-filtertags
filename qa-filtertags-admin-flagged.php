<?php
/*
	Filter Tags plugin - replacement for qa-include/pages/admin/admin-flagged.php

	Why this file exists: core's qa_db_flagged_post_qs_selectspec() (in qa-include/db/selects.php)
	does not call qa_to_override(__FUNCTION__), so it cannot be patched through Q2A's normal plugin
	override mechanism - any attempt to do so silently has no effect on the actual admin/flagged
	listing (it only ever affects code that calls the function through qa_call_override(), which
	never happens for this particular function). To make the "Exclude Users" / Visibility / Type /
	Reason filters added by this plugin's toolbar (see qa-filtertags-layer.php) actually filter the
	posts shown on this page - and not just the pagination count - this page takes over the
	'admin/flagged' request entirely (see the qa_page_routing() override in
	qa-filtertags-overrides.php) and applies the filters directly to the selectspec before running
	the query. Besides that, this is otherwise a faithful copy of the core page.
*/

if (!defined('QA_VERSION')) {
	header('Location: ../../');
	exit;
}

require_once QA_INCLUDE_DIR . 'app/admin.php';
require_once QA_INCLUDE_DIR . 'db/selects.php';
require_once QA_INCLUDE_DIR . 'app/format.php';


// Find most flagged questions, answers, comments

$userid = qa_get_logged_in_userid();
$start = qa_get_start();
$pageSize = (int)qa_opt('page_size_qs');

$qa_filtertags_selectspec = qa_db_flagged_post_qs_selectspec($userid, $start, true, $pageSize);

// Apply this plugin's admin/flagged filter toolbar (Visibility / Type / Reason / Exclude Users)
// to the inner subquery that actually selects which flagged posts are returned.
$qa_filtertags_filters = [];

if (qa_get('filter_public')) {
	$globalTags = qa_opt('qa-filtertags-global');
	if (!empty($globalTags)) {
		$tags = array_map('trim', explode(',', $globalTags));
		$escaped = implode("','", array_map('qa_db_escape_string', $tags));
		// Exclude posts whose parent question has any global filter tag
		$qa_filtertags_filters[] = "(CASE WHEN LEFT(^posts.type,1)='Q' THEN ^posts.postid ELSE ^posts.parentid END) NOT IN ("
			. "SELECT pt.postid FROM ^posttags pt JOIN ^words wd ON pt.wordid=wd.wordid WHERE wd.word IN ('" . $escaped . "')"
			. ")";
	}
}

$qa_filtertags_posttype = qa_get('filter_type');
if ($qa_filtertags_posttype && in_array($qa_filtertags_posttype, ['Q', 'A', 'C'])) {
	$qa_filtertags_filters[] = "LEFT(^posts.type,1)='" . $qa_filtertags_posttype . "'";
}

$qa_filtertags_reasonid = qa_get('filter_reason');
if ($qa_filtertags_reasonid === 'none') {
	$qa_filtertags_filters[] = "^posts.postid NOT IN (SELECT postid FROM ^flagreasons)";
} elseif ($qa_filtertags_reasonid && is_numeric($qa_filtertags_reasonid) && (int)$qa_filtertags_reasonid >= 1 && (int)$qa_filtertags_reasonid <= 7) {
	$qa_filtertags_filters[] = "^posts.postid IN (SELECT postid FROM ^flagreasons WHERE reasonid=" . (int)$qa_filtertags_reasonid . ")";
}

$qa_filtertags_excludeusers = qa_get('filter_exclude_users');
if ($qa_filtertags_excludeusers) {
	$qa_filtertags_excludeids = array_filter(array_map('intval', explode(',', $qa_filtertags_excludeusers)));
	if (!empty($qa_filtertags_excludeids)) {
		$qa_filtertags_filters[] = "^posts.userid NOT IN (" . implode(',', $qa_filtertags_excludeids) . ")";
	}
}

if (!empty($qa_filtertags_filters)) {
	$qa_filtertags_filtersql = ' AND ' . implode(' AND ', $qa_filtertags_filters);
	$qa_filtertags_selectspec['source'] = str_replace(
		"WHERE flagcount>0 AND type IN ('Q', 'A', 'C')",
		"WHERE flagcount>0 AND type IN ('Q', 'A', 'C')" . $qa_filtertags_filtersql,
		$qa_filtertags_selectspec['source']
	);
}

$questions = qa_db_select_with_pending($qa_filtertags_selectspec);


// Check admin privileges (do late to allow one DB query)

if (qa_user_maximum_permit_error('permit_hide_show')) {
	$qa_content = qa_content_prepare();
	$qa_content['error'] = qa_lang_html('users/no_permission');
	return $qa_content;
}


// Check to see if any were cleared or hidden here

$pageerror = qa_admin_check_clicks();


// Remove questions the user has no permission to hide/show

if (qa_user_permit_error('permit_hide_show')) { // if user not allowed to show/hide all posts
	foreach ($questions as $index => $question) {
		if (qa_user_post_permit_error('permit_hide_show', $question)) {
			unset($questions[$index]);
		}
	}
}


// Get information for users

$usershtml = qa_userids_handles_html(qa_any_get_userids_handles($questions));


// Prepare content for theme

$qa_content = qa_content_prepare();

$qa_content['title'] = qa_lang_html('admin/most_flagged_title');
$qa_content['error'] = isset($pageerror) ? $pageerror : qa_admin_page_error();

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
		$htmloptions['tagsview'] = ($question['obasetype'] == 'Q');
		$htmloptions['answersview'] = false;
		$htmloptions['viewsview'] = false;
		$htmloptions['contentview'] = true;
		$htmloptions['flagsview'] = true;
		$htmloptions['elementid'] = $elementid;

		$htmlfields = qa_any_to_q_html_fields($question, $userid, qa_cookie_get(), $usershtml, null, $htmloptions);

		if (isset($htmlfields['what_url'])) // link directly to relevant content
			$htmlfields['url'] = $htmlfields['what_url'];

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

		$qa_content['q_list']['qs'][] = $htmlfields;
	}

	// Manual count query - matches core's cached-count behaviour when no filters are active.
	// When any of this plugin's filters are active, qa-filtertags-layer.php's page_links()
	// recomputes this total to reflect the filtered count instead.
	$totalflaged = qa_opt('cache_flaggedcount');

	// Add page links
	$qa_content['page_links'] = qa_html_page_links(
										qa_request(),     // Current request
										$start,           // Current start offset
										$pageSize,        // how many posts per page
										$totalflaged,     // Total number of items
										2,             // number of page links before and after current
										array(),          // Extra query params
										''                // Anchor
									);

} else
	$qa_content['title'] = qa_lang_html('admin/no_flagged_found');


$qa_content['navigation']['sub'] = qa_admin_sub_navigation();
$qa_content['script_rel'][] = 'qa-content/qa-admin.js?' . QA_VERSION;


return $qa_content;

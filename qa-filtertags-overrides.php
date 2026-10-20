
<?php
//handled in exam overrides for now
 function not_active_qa_db_posts_basic_selectspec($voteuserid=null, $full=false, $user=true)
{
	if(qa_get_logged_in_userid())
		return  qa_db_posts_basic_selectspec_base($voteuserid, $full, $user);
	$globalfiltertagstring = implode("','", explode(",", qa_opt('qa-filtertags-global')));
	$res = qa_db_posts_basic_selectspec_base($voteuserid, $full, $user);
	$res['source'] .= " join ^posts gf on ^posts.postid = gf.postid  and gf.postid not in(select postid  from ^posttags pt where pt.wordid  in (select wordid from ^words wd where wd.word in ('".$globalfiltertagstring."'))) ";
	return $res;
}

/**
 * IMPORTANT: core's qa_db_flagged_post_qs_selectspec() (in qa-include/db/selects.php) does NOT call
 * qa_to_override(__FUNCTION__), so it cannot be patched through Q2A's standard override mechanism -
 * any attempt to override it here would silently never run. Instead, the admin/flagged listing is
 * taken over entirely via the qa_page_routing() override below, which points that request at our own
 * qa-filtertags-admin-flagged.php page. That page calls the real, unmodified
 * qa_db_flagged_post_qs_selectspec() directly and then applies the filters below to its result.
 */
function qa_page_routing()
{
	$routing = qa_page_routing_base();
	$routing['admin/flagged'] = QA_FILTERTAGS_INCLUDE_RELATIVE . 'qa-filtertags-admin-flagged.php';

	return $routing;
}
?>


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

function qa_db_flagged_post_qs_selectspec($voteuserid, $start, $fullflagged = false, $count = null)
{
	$selectspec = qa_db_flagged_post_qs_selectspec_base($voteuserid, $start, $fullflagged, $count);

	$filters = [];

	// Filter: show only posts from public (non-filtertag) questions
	if (qa_get('filter_public')) {
		$globalTags = qa_opt('qa-filtertags-global');
		if (!empty($globalTags)) {
			$tags = array_map('trim', explode(',', $globalTags));
			$escaped = implode("','", array_map('qa_db_escape_string', $tags));
			// Exclude posts whose parent question has any global filter tag
			$filters[] = "(CASE WHEN LEFT(^posts.type,1)='Q' THEN ^posts.postid ELSE ^posts.parentid END) NOT IN ("
				. "SELECT pt.postid FROM ^posttags pt JOIN ^words wd ON pt.wordid=wd.wordid WHERE wd.word IN ('" . $escaped . "')"
				. ")";
		}
	}

	// Filter by post type (Q, A, C)
	$postType = qa_get('filter_type');
	if ($postType && in_array($postType, ['Q', 'A', 'C'])) {
		$filters[] = "LEFT(^posts.type,1)='" . $postType . "'";
	}

	// Filter by flag reason
	$reasonId = qa_get('filter_reason');
	if ($reasonId === 'none') {
		$filters[] = "^posts.postid NOT IN (SELECT postid FROM ^flagreasons)";
	} elseif ($reasonId && is_numeric($reasonId) && (int)$reasonId >= 1 && (int)$reasonId <= 7) {
		$filters[] = "^posts.postid IN (SELECT postid FROM ^flagreasons WHERE reasonid=" . (int)$reasonId . ")";
	}

	// Apply all filters to the inner subquery
	if (!empty($filters)) {
		$filterSql = ' AND ' . implode(' AND ', $filters);
		$selectspec['source'] = str_replace(
			"WHERE flagcount>0 AND type IN ('Q', 'A', 'C')",
			"WHERE flagcount>0 AND type IN ('Q', 'A', 'C')" . $filterSql,
			$selectspec['source']
		);
	}

	return $selectspec;
}
?>


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
?>

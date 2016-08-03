
<?php
 function qa_db_posts_basic_selectspec($voteuserid=null, $full=false, $user=true)
{
	$globalfiltertagstring = implode("','", explode(",", qa_opt('qa-filtertags-global')));
	$res = qa_db_posts_basic_selectspec_base($voteuserid, $full, $user);
	$res['source'] .= " and ^posts.postid not in(select postid  from ^posttags pt where pt.wordid  in (select wordid from ^words wd where wd.word in ('$globalfiltertagstring'))) ";
	return $res;
}
?>

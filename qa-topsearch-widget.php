<?php

class qa_topsearch_widget {

	var $urltoroot;

	function load_module($directory, $urltoroot)
	{
		$this->urltoroot = $urltoroot;
	}

	function allow_template($template)
	{

		return true;
	}

	function allow_region($region)
	{
		return true;

	}

	function output_widget($region, $place, $themeobject, $template, $request, $qa_content)
	{

		require_once QA_INCLUDE_DIR.'db/selects.php';


		$out='<div class="qa-top-search-title"><h2>'.qa_opt('qa-topsearch-plugin-title').'</h2></div>';
		$out.='<div class="qa-top-search">';
		$limit = 5 * qa_opt('qa-topsearch-plugin-count');
		$query = "SELECT params, event  FROM ^eventlog  WHERE 
			event like '".qa_opt('qa-topsearch-plugin-param')."' 
			and datetime >= NOW() - INTERVAL ".qa_opt('qa-topsearch-plugin-interval-days')." day
			ORDER BY datetime DESC
			LIMIT ".$limit ;

		$result = qa_db_query_sub($query);

		$search = qa_db_read_all_assoc($result);
		$strings = array();


		for($i = 0; $i < count($search); $i++){
			$temp = explode("\t",$search[$i]['params']);
			$strings[] = substr($temp[0],6);
		}
		$outr = array();
		for($i=0; $i <count($strings); $i++)
		{
			if(isset($outr[$strings[$i]]))
				$outr[$strings[$i]]++;
			else
				$outr[$strings[$i]] = 0;
		}
		if(qa_opt('qa-topsearch-plugin-recent') !== '1')
		arsort($outr);
		$cnt = qa_opt('qa-topsearch-plugin-count');
		$alltags = array();
		if(qa_opt('qa-topsearch-plugin-param') === 'tagsearch') {
			$querypage = 'tag-search-page';
			$query = "select word from ^words where wordid in (select wordid from ^posttags)";
			$result = qa_db_query_sub($query);
			$alltagsr = qa_db_read_all_assoc($result);
			$alltags[] = array();
			for($i = 0; $i < count($alltagsr); $i++)
				$alltags[] = $alltagsr[$i]['word'];
		}

		else
			$querypage = 'search';
		$icount = 0;
		foreach ($outr as $key => $value)
		{
			if(qa_opt('qa-topsearch-plugin-param') === 'tagsearch')
			{
				$tags = explode(" ", $key);
				for($i = 0; $i < count($tags); $i++)
				{
					if(!in_array($tags[$i], $alltags)){
						break;
					}
				}
				if($i != count($tags))
					continue;
			}


			$out .='	<span class="qa-top-search-item"> <a href="'.qa_opt('site_url').$querypage.'?q='.urlencode($key).'">'.$key.'</a> </span>';
			$icount++;
			if($icount>$cnt)break;

		}
		$out .='</div>';
		$output = '<div class="topsearch-widget-container">'.$out.'</div>';

		$themeobject->output(
				$output
				);			
	}
};


/*
   Omit PHP closing tag to help avoid accidental output
 */

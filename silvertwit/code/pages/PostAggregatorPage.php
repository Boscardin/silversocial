<?php

/**
 * @author marcus@silverstripe.com.au
 * @license BSD License http://silverstripe.org/bsd-license/
 */
class PostAggregatorPage extends Page {
	public static $db = array();
}

class PostAggregatorPage_Controller extends Page_Controller {

	static $dependencies = array(
		'microBlogService'		=> '%$MicroBlogService',
		'securityContext'		=> '%$SecurityContext',
	);
	
	/** 
	 * @var MicroBlogService
	 */
	public $microBlogService;
	
	protected $tags = '';
	
	public function init() {
		parent::init();
		Requirements::javascript(THIRDPARTY_DIR . '/jquery-form/jquery.form.js');
		Requirements::javascript('microblog/javascript/timeline.js');
	}

	public function Timeline() {
		$replies = (bool) $this->request->getVar('replies');
		$since = $this->request->getVar('since');
		$before = $this->request->getVar('before');
		$page = $this->request->getVar('page');
		
		$tags = $this->request->getVar('tags') ? $this->request->getVar('tags') : $this->tags;

		if (strlen($page)) {
			$before = array(
				'Page'			=> $page,
			);
		}
		
		if (strlen($tags)) {
			$tags = explode(',', $tags);
		} else {
			$tags = array();
		}
		
		$timeline = $this->microBlogService->getStatusUpdates(null, 'WilsonRating', $since, $before, !$replies, $tags);
		return trim($this->customise(array('Posts' => $timeline, 'SortBy' => 'rating'))->renderWith('Timeline'));
	}
	
	public function tag() {
		$this->tags = $this->getRequest()->param('ID');
		return array();
	}
}

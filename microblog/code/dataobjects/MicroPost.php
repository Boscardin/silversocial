<?php

/**
 * @author marcus@silverstripe.com.au
 * @license BSD License http://silverstripe.org/bsd-license/
 */
class MicroPost extends DataObject implements Syncroable {
	public static $db = array(
		'Title'			=> 'Varchar(255)',
		'Content'		=> 'Text',
		'Author'		=> 'Varchar(255)',
		'OriginalLink'	=> 'Varchar',
		'IsOembed'		=> 'Boolean',
		'Deleted'		=> 'Boolean',
		'NumReplies'	=> 'Int',
	);

	public static $has_one = array(
		
		'ThreadOwner'	=> 'PublicProfile',			// owner of the thread this is in
		'OwnerProfile'	=> 'PublicProfile',			// owner of the actual post itself
		'Parent'		=> 'MicroPost',
		'Thread'		=> 'MicroPost',
		'Attachment'	=> 'File',

		'PermSource'	=> 'PermissionParent',
	);

	public static $has_many = array(
		'Replies'		=> 'MicroPost.Parent',
	);
	
	public static $defaults = array(
		'PublicAccess'		=> false,
		'InheritPerms'		=> true,		// we'll have  default container set soon
	);
	
	public static $extensions = array(
		'Rateable',
		'Restrictable',
		'TaggableExtension',
		'SyncroableExtension',
	);

	public static $summary_fields = array(
		'Title', 
		'Content',
		'Created'
	);
	
	public static $searchable_fields = array(
		'Title',
		'Content'
	);
	
	public static $dependencies = array(
		'queuedJobService'		=> '%$QueuedJobService',
		'microBlogService'		=> '%$MicroBlogService',
		'securityContext'		=> '%$SecurityContext',
		'syncrotronService'		=> '%$SyncrotronService',
	);
	
	public static $default_sort = 'ID DESC';

	/**
	 * Do we automatically detect oembed data and change comments? 
	 * 
	 * Override using injector configuration
	 * 
	 * @var boolean
	 */
	public $oembedDetect = true;
	
	/**
	 * @var QueuedJobService
	 */
	public $queuedJobService;
	
	/**
	 * @var MicroBlogService
	 */
	public $microBlogService;
	
	/**
	 * @var SecurityContext
	 */
	public $securityContext;
	
	/**
	 * @var SyncrotronService 
	 */
	public $syncrotronService;

	public function onBeforeWrite() {
		parent::onBeforeWrite();
		$member = $this->securityContext->getMember();
		if (!$this->ThreadOwnerID) {
			if ($this->ParentID) {
				$this->ThreadOwnerID = $this->Parent()->ThreadOwnerID;
			} else {
				$this->ThreadOwnerID = $member->ProfileID;
			}
		}
		
		if (!$this->ID) {
			$this->postProcess = true;
		}

		if (!$this->OwnerProfileID) {
			$this->OwnerProfileID = $member->ProfileID;
			$this->Author = $this->securityContext->getMember()->getTitle();
		}
	}
	
	public function onAfterWrite() {
		parent::onAfterWrite();
		if ($this->postProcess) {
			$this->queuedJobService->queueJob(new ProcessPostJob($this));
			$this->postProcess = false;
		}
	}
	
	/**
	 * Handle the wilson rating specially 
	 * 
	 * @param type $field
	 * @return string 
	 */
	public function hasOwnTableDatabaseField($field) {
		if ($field == 'WilsonRating') {
			return "Double";
		}
		return parent::hasOwnTableDatabaseField($field);
	}

	public function IsImage() {
		$url = filter_var($this->Content, FILTER_VALIDATE_URL);
		$pattern = '!^https?://([a-z0-9\-\.\/\_]+\.(?:jpe?g|png|gif))$!Ui';
		return strlen($url) && preg_match($pattern, $url);
	}

	/**
	 * When 'deleting' an object, we actually just remove all its content 
	 */
	public function delete() {
		if ($this->checkPerm('Delete')) {
			$this->Tags()->removeAll();
			// if we have replies, we can't delete completely!
			if ($this->Replies()->exists() && $this->Replies()->count() > 0) {
				$count = $this->Replies()->count();
				$item = $this->Replies()->first();
				$this->Deleted = true;
				$this->Content = _t('MicroPost.DELETED', '[deleted]');
				$this->Author = $this->Content;
				$this->write();
			} else {
				return parent::delete();
			}
		}
	}

	/**
	 * handles SiteTree::canAddChildren, useful for other types too
	 */
	public function canAddChildren() {
		if ($this->checkPerm('View')) {
			return true;
		} else {
			return false;
		}
	}

	public function formattedPost() {
		return Convert::raw2xml($this->Content);
	}
	
	public function Link() {
		return 'timeline/show/' . $this->ID;
	}
	
	public function AbsoluteLink() {
		return Director::absoluteURL($this->Link());
	}

	public function Posts() {
		return $this->microBlogService->getRepliesTo($this);
	}
	
	/**
	 * We need to define a  permission source to ensure the 
	 * ParentID isn't used for permission inheritance 
	 */
	public function permissionSource() {
		if ($this->PermSourceID) {
			return $this->PermSource();
		}
		
		// otherwise, find a post by this user and use the shared parent
		$owner = $this->Owner();
		if ($owner && $owner->exists()) {
			$source = $owner->postPermissionSource();
			$this->PermSourceID = $source->ID;
			// TODO: Remove this; it's only used until all posts have an appropriate permission source...
			Restrictable::set_enabled(false);
			$this->write();
			Restrictable::set_enabled(true);
			return $source;
		}
	}

	public function forSyncro() {
		$props = $this->syncrotronService->syncroObject($this);
		unset($props['PermSourceID']);
		
		$props['Post_ThreadEmail'] = $this->ThreadOwner()->Email;
		$props['Post_OwnerEmail'] = $this->OwnerProfile()->Email;
		
		return $props;
	}

	public function fromSyncro($properties) {
		$this->syncrotronService->unsyncroObject($properties, $this);
		
		// now make sure the other things are aligned
		if (isset($properties->Post_ThreadEmail)) {
			$profile = DataList::create('PublicProfile')->filter(array('Email' => $properties->Post_ThreadEmail))->first();
			if ($profile) {
				$this->ThreadOwnerID = $profile->ID;
			}
		}

		if (isset($properties->Post_OwnerEmail)) {
			$profile = DataList::create('PublicProfile')->filter(array('Email' => $properties->Post_OwnerEmail))->first();
			if ($profile) {
				$this->OwnerProfileID = $profile->ID;
			}
		}

		// bind the correct permission source
		$this->permissionSource();
	}
}

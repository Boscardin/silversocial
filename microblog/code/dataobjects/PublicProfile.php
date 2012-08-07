<?php

/**
 * Information about a user that's visible to everyone
 * 
 * @author marcus@silverstripe.com.au
 * @license BSD License http://silverstripe.org/bsd-license/
 */
class PublicProfile extends DataObject {
	public static $db = array(
		'FirstName' => 'Varchar',
		'Surname' => 'Varchar',
		'Email' => 'Varchar(256)', // See RFC 5321, Section 4.5.3.1.3.
		'MemberID'	=> 'Int',
		'Votes'		=> 'Int',
	);
	
	public function Link() {
		$microblog = DataObject::get_one('SiteDashboardPage', '"ParentID" = 0');
		return $microblog->Link('board/main/' . $this->MemberID);
	}

	public function canView($member=null) {
		return true;
	}
	
	public function member() {
		return Member::get()->filter(array('ID' => $this->MemberID))->first();
	}
}

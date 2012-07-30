<?php

/**
 * @author marcus@silverstripe.com.au
 * @license BSD License http://silverstripe.org/bsd-license/
 */
class SilvertwitSecurity extends Security {
	
	public function login() {
		// Event handler for pre-login, with an option to let it break you out of the login form
		$eventResults = $this->extend('onBeforeSecurityLogin');
		// If there was a redirection, return
		if($this->redirectedTo()) return;
		// If there was an SS_HTTPResponse object returned, then return that
		else if($eventResults) {
			foreach($eventResults as $result) {
				if($result instanceof SS_HTTPResponse) return $result;
			}
		}
		
		
		$customCSS = project() . '/css/tabs.css';
		if(Director::fileExists($customCSS)) {
			Requirements::css($customCSS);
		}

		if(class_exists('SiteTree')) {
			$tmpPage = new Page();
			$tmpPage->Title = _t('Security.LOGIN', 'Log in');
			$tmpPage->URLSegment = "Security";
			// Disable ID-based caching  of the log-in page by making it a random number
			$tmpPage->ID = -1 * rand(1,10000000);

			$controller = new Page_Controller($tmpPage);
			$controller->setDataModel($this->model);
			$controller->init();
			//Controller::$currentController = $controller;
		} else {
			$controller = $this;
		}


		$content = '';
		$forms = $this->GetLoginForms();
		if(!count($forms)) {
			user_error('No login-forms found, please use Authenticator::register_authenticator() to add one', E_USER_ERROR);
		}
		
		$otherAuth = array();
		
		// only display tabs when more than one authenticator is provided
		// to save bandwidth and reduce the amount of custom styling needed 
		if(count($forms) > 1) {
			foreach($forms as $form) {
				if ($form instanceof MemberLoginForm) {
					$content .= $form->forTemplate();
				} else {
					$form->Fields()->removeByName('Remember');
					$otherAuth[$form->getAuthenticator()->get_name()] = $form->forTemplate();
				}
			}
		} else {
			$content .= $forms[0]->forTemplate();
		}

		$customData = array();
		if (count($otherAuth)) {
			$customData = $otherAuth;
		}

		$customData['Form'] = $content;
		
		if(strlen($message = Session::get('Security.Message.message')) > 0) {
			$message_type = Session::get('Security.Message.type');
			if($message_type == 'bad') {
				$message = "<p class=\"message $message_type\">$message</p>";
			} else {
				$message = "<p>$message</p>";
			}

			$customData['Content'] = $message;
		} 
		
		$customisedController = $controller->customise($customData);
		
		Session::clear('Security.Message');

		// custom processing
		return $customisedController->renderWith(array('Security_login', 'Security', $this->stat('template_main'), 'BlankPage'));
	}
	
	public function logout($redirect = true) {
		Restrictable::set_enabled(false);
		$member = Member::currentUser();
		Restrictable::set_enabled(true);

		if($member) {
			// run the logout as an admin so we can update the user object
			singleton('TransactionManager')->run(array($member, 'logOut'), Security::findAnAdministrator());
		}

		if($redirect) $this->redirectBack();

		return '';
	}
	
	/**
	 * Factory method for the lost password form
	 *
	 * @return Form Returns the lost password form
	 */
	public function LostPasswordForm() {
		return MemberLoginForm::create(			$this,
			'LostPasswordForm',
			new FieldList(
				new EmailField('Email', _t('Member.EMAIL', 'Email'))
			),
			new FieldList(
				new FormAction(
					'forgotPassword',
					_t('Security.BUTTONSEND', 'Send me the password reset link')
				)
			),
			false
		);
	}
	
	/**
	 * Forgot password form handler method
	 *
	 * This method is called when the user clicks on "I've lost my password"
	 *
	 * @param array $data Submitted data
	 */
	function forgotPassword($data) {
		$SQL_data = Convert::raw2sql($data);
		$SQL_email = $SQL_data['Email'];
		$member = DataObject::get_one('Member', "\"Email\" = '{$SQL_email}'");

		if($member) {
			Restrictable::set_enabled(false);
			$member->generateAutologinHash();

			$e = Member_ForgotPasswordEmail::create();
			$e->populateTemplate($member);
			$e->populateTemplate(array(
				'PasswordResetLink' => Security::getPasswordResetLink($member->AutoLoginHash)
			));
			$e->setTo($member->Email);
			$e->send();
			Restrictable::set_enabled(true);

			$this->redirect('Security/passwordsent/' . urlencode($data['Email']));
		} elseif($data['Email']) {
			// Avoid information disclosure by displaying the same status,
			// regardless wether the email address actually exists
			$this->redirect('Security/passwordsent/' . urlencode($data['Email']));
		} else {
			$this->sessionMessage(
				_t('Member.ENTEREMAIL', 'Please enter an email address to get a password reset link.'),
				'bad'
			);
			
			$this->redirect('Security/lostpassword');
		}
	}
}

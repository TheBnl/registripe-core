<?php
/**
 * A calendar event that can people can register to attend.
 *
 * @package silverstripe-eventmanagement
 */
class RegisterableEvent extends CalendarEvent {

	public static $db = array(
		'OneRegPerEmail'        => 'Boolean',
		'RegEmailConfirm'       => 'Boolean',
		'EmailConfirmMessage'   => 'Varchar(255)',
		'AfterConfirmTitle'     => 'Varchar(255)',
		'AfterConfirmContent'   => 'HTMLText',
		'UnRegEmailConfirm'     => 'Boolean',
		'AfterConfUnregTitle'   => 'Varchar(255)',
		'AfterConfUnregContent' => 'HTMLText',
		'AfterConfirmContent'   => 'HTMLText',
		'EmailNotifyChanges'    => 'Boolean',
		'NotifyChangeFields'    => 'Text',
		'RequireLoggedIn'       => 'Boolean',
		'AfterRegTitle'         => 'Varchar(255)',
		'AfterRegContent'       => 'HTMLText',
		'AfterUnregTitle'       => 'Varchar(255)',
		'AfterUnregContent'     => 'HTMLText'
	);

	public static $has_many = array(
		'Tickets'       => 'EventTicket',
		'DateTimes'     => 'RegisterableDateTime',
		'Registrations' => 'EventRegistration'
	);

	public static $defaults = array(
		'AfterRegTitle'         => 'Thanks For Registering',
		'AfterRegContent'       => '<p>Thanks for registering! We look forward to seeing you.</p>',
		'EmailConfirmMessage'   => 'Important: You must check your emails and confirm your registration before it is valid.',
		'AfterConfirmTitle'     => 'Registration Confirmed',
		'AfterConfirmContent'   => '<p>Thanks! Your registration has been confirmed</p>',
		'AfterUnregTitle'       => 'Registration Canceled',
		'AfterUnregContent'     => '<p>Your registration has been canceled.</p>',
		'AfterConfUnregTitle'   => 'Un-Registration Confirmed',
		'AfterConfUnregContent' => '<p>Your registration has been canceled.</p>',
		'NotifyChangeFields'    => 'StartDate,EndDate,StartTime,EndTime'
	);

	public function getCMSFields() {
		Requirements::javascript(THIRDPARTY_DIR . '/jquery/jquery.js');
		Requirements::javascript(THIRDPARTY_DIR . '/jquery-livequery/jquery.livequery.js');
		Requirements::javascript('eventmanagement/javascript/RegisterableEventCms.js');

		$fields = parent::getCMSFields();

		$fields->addFieldsToTab('Root.Content.Tickets', array(
			new HeaderField('TicketTypesHeader', $this->fieldLabel('TicketTypesHeader')),
			new ComplexTableField($this, 'Tickets', 'EventTicket')
		));

		$changeFields = singleton('RegisterableDateTime')->fieldLabels(false);
		$fields->addFieldsToTab('Root.Content.Registration', array(
			new HeaderField('EmailSettingsHeader', $this->fieldLabel('EmailSettingsHeader')),
			new CheckboxField('OneRegPerEmail', $this->fieldLabel('OneRegPerEmail')),
			new CheckboxField('RegEmailConfirm', $this->fieldLabel('RegEmailConfirm')),
			new TextField('EmailConfirmMessage', $this->fieldLabel('EmailConfirmMessage')),
			new TextField('AfterConfirmTitle', $this->fieldLabel('AfterConfirmTitle')),
			new HtmlEditorField('AfterConfirmContent', $this->fieldLabel('AfterConfirmContent'), 5),
			new CheckboxField('UnRegEmailConfirm', $this->fieldLabel('UnRegEmailConfirm')),
			new TextField('AfterConfUnregTitle', $this->fieldLabel('AfterConfUnregTitle')),
			new HtmlEditorField('AfterConfUnregContent', $this->fieldLabel('AfterConfUnregContent'), 5),
			new CheckboxField('EmailNotifyChanges', $this->fieldLabel('EmailNotifyChanges')),
			new CheckboxSetField('NotifyChangeFields', $this->fieldLabel('NotifyChangeFields'), $changeFields),
			new HeaderField('MemberSettingsHeader', $this->fieldLabel('MemberSettingsHeader')),
			new CheckboxField('RequireLoggedIn', $this->fieldLabel('RequireLoggedIn'))
		));

		$fields->addFieldsToTab('Root.Content.AfterRegistration', array(
			new TextField('AfterRegTitle', $this->fieldLabel('AfterRegTitle')),
			new HtmlEditorField('AfterRegContent', $this->fieldLabel('AfterRegContent'))
		));

		$fields->addFieldsToTab('Root.Content.AfterUnregistration', array(
			new TextField('AfterUnregTitle', $this->fieldLabel('AfterUnregTitle')),
			new HtmlEditorField('AfterUnregContent', $this->fieldLabel('AfterUnregContent'))
		));

		$registrations = new ComplexTableField(
			$this, 'Registrations', 'EventRegistration', null, null, '"Status" = \'Valid\''
		);
		$registrations->setPermissions(array('show', 'print', 'export'));

		$canceled = new ComplexTableField(
			$this, 'Registations', 'EventRegistration', null, null, '"Status" = \'Canceled\''
		);
		$canceled->setPermissions(array('show', 'print', 'export'));

		$fields->addFieldToTab('Root', new Tab('Registrations'), 'Behaviour');
		$fields->addFieldsToTab('Root.Registrations', array(
			new HeaderField('RegistrationsHeader', $this->fieldLabel('Registrations')),
			$registrations,
			new ToggleCompositeField('CanceledRegistrations', 'Canceled Registrations', $canceled)
		));

		if ($this->RegEmailConfirm) {
			$times = $this->DateAndTime();

			if (!$times) {
				$count = 0;
			} else {
				$count = DB::query(sprintf(
					'SELECT COUNT(*) FROM "EventRegistration" WHERE '
					. '"Status" = \'Unconfirmed\' AND "TimeID" IN (%s)',
					implode(', ', $times->map('ID', 'ID'))
				));
				$count = $count->value();
			}

			$unconfirmed = _t(
				'EventManagement.NUMUNCONFIRMEDREG',
				'There are %d unconfirmed registrations.');

			$fields->addFieldToTab('Root.Registrations', new LiteralField(
				'UnconfirmedRegistrations', sprintf("<p>$unconfirmed</p>", $count)
			));
		}

		// Add a tab allowing admins to invite people from, as well as view
		// people who have been invited.
		$fields->addFieldToTab('Root', new Tab('Invitations'), 'Behaviour');
		$fields->addFieldsToTab('Root.Invitations', array(
			new HeaderField('InvitationsHeader', $this->fieldLabel('InvitationsHeader')),
			new EventInvitationField($this, 'Invitations')
		));

		return $fields;
	}

	public function fieldLabels() {
		return array_merge(parent::fieldLabels(), array(
			'TicketTypesHeader' => _t('EventManagement.TICKETTYPES', 'Ticket Types'),
			'Registrations' => _t('EventManagement.REGISTATIONS', 'Registrations'),
			'EmailSettingsHeader' => _t('EventManagement.EMAILSETTINGS', 'Email Settings'),
			'OneRegPerEmail' => _t('EventManagement.ONEREGPEREMAIL', 'Limit to one registration per email address?'),
			'RegEmailConfirm' => _t('EventManagement.REQEMAILCONFIRM', 'Require email confirmation to complete free registrations?'),
			'EmailConfirmMessage' => _t('EventManagement.EMAILCONFIRMINFOMSG', 'Email confirmation information message'),
			'AfterConfirmTitle' => _t('EventManagement.AFTERCONFIRMTITLE', 'After confirmation title'),
			'AfterConfirmContent' => _t('EventManagement.AFTERCONFIRMCONTENT', 'After confirmation content'),
			'UnRegEmailConfirm' => _t('EventManagement.REQEMAILUNREGCONFIRM', 'Require email confirmation to un-register?'),
			'AfterConfUnregTitle' => _t('EventManagement.AFTERUNREGCONFTITLE', 'After un-registration confirmation title'),
			'AfterConfUnregContent' => _t('EventManagement.AFTERUNREGCONFCONTENT', 'After un-registration confirmation content'),
			'EmailNotifyChanges' => _t('EventManagement.EMAILNOTIFYCHANGES', 'Notify registered users of event changes via email?'),
			'NotifyChangeFields' => _t('EventManagement.NOTIFYWHENTHESECHANGE', 'Notify users when these fields change'),
			'MemberSettingsHeader' => _t('EventManagement.MEMBERSETTINGS', 'Member Settings'),
			'RequireLoggedIn' => _t('EventManagement.REQUIREDLOGGEDIN', 'Require users to be logged in to register?'),
			'AfterRegTitle' => _t('EventManagement.AFTERREGTITLE', 'After registration title'),
			'AfterRegContent' => _t('EventManagement.AFTERREGCONTENT', 'After registration content'),
			'AfterUnregTitle' => _t('EventManagement.AFTERUNREGTITLE', 'After un-registration title'),
			'AfterUnregContent' => _t('EventManagement.AFTERUNREGCONTENT', 'After un-registration content'),
			'InvitationsHeader' => _t('EventManagement.EVENTINVITES', 'Event Invitations')
		));
	}

}

/**
 * @package silverstripe-eventmanagement
 */
class RegisterableEvent_Controller extends CalendarEvent_Controller {

	public static $allowed_actions = array(
		'details',
		'registration'
	);

	/**
	 * Shows details for an individual event date time, as well as forms for
	 * registering and unregistering.
	 *
	 * @param  SS_HTTPRequest $request
	 * @return array
	 */
	public function details($request) {
		$id   = $request->param('ID');
		$time = DataObject::get_by_id('RegisterableDateTime', (int) $id);

		if (!$time || $time->EventID != $this->ID) {
			$this->httpError(404);
		}

		$request->shift();
		$request->shiftAllParams();

		return new EventTimeDetailsController($this, $time);
	}

	/**
	 * Allows a user to view the details of their registration.
	 *
	 * @param  SS_HTTPRequest $request
	 * @return EventRegistrationDetailsController
	 */
	public function registration($request) {
		$id   = $request->param('ID');
		$rego = DataObject::get_by_id('EventRegistration', (int) $id);

		if (!$rego || $rego->Time()->EventID != $this->ID) {
			$this->httpError(404);
		}

		$request->shift();
		$request->shiftAllParams();

		return new EventRegistrationDetailsController($this, $rego);
	}

}

<?php
/**
 * A calendar event that can people can register to attend.
 *
 * @package silverstripe-eventmanagement
 */
class RegisterableEvent extends CalendarEvent {

	public static $db = array(
		'RegEmailConfirm'     => 'Boolean',
		'AfterConfirmTitle'   => 'Varchar(255)',
		'AfterConfirmContent' => 'HTMLText',
		'LimitedPlaces'       => 'Boolean',
		'NumPlaces'           => 'Int',
		'MultiplePlaces'      => 'Boolean',
		'MaxPlaces'           => 'Int',
		'RequireLoggedIn'     => 'Boolean',
		'OneRegPerMember'     => 'Boolean',
		'AfterRegTitle'       => 'Varchar(255)',
		'AfterRegContent'     => 'HTMLText'
	);

	public static $has_many = array(
		'DateTimes'     => 'RegisterableDateTime',
		'Registrations' => 'EventRegistration'
	);

	public static $defaults = array(
		'AfterRegTitle'       => 'Thanks For Registering',
		'AfterRegContent'     => '<p>Thanks for registering! We look forward to seeing you.</p>',
		'AfterConfirmTitle'   => 'Registration Confirmed',
		'AfterConfirmContent' => '<p>Thanks! Your registration has been confirmed</p>'
	);

	public function getCMSFields() {
		Requirements::javascript(THIRDPARTY_DIR . '/jquery/jquery.js');
		Requirements::javascript(THIRDPARTY_DIR . '/jquery-livequery/jquery.livequery.js');
		Requirements::javascript('eventmanagement/javascript/RegisterableEventCms.js');

		$fields = parent::getCMSFields();

		$fields->addFieldsToTab('Root.Content.Registration', array(
			new HeaderField('EmailConfirmationHeader', $this->fieldLabel('EmailConfirmationHeader')),
			new CheckboxField('RegEmailConfirm', $this->fieldLabel('RegEmailConfirm')),
			new TextField('AfterConfirmTitle', $this->fieldLabel('AfterConfirmTitle')),
			new HtmlEditorField('AfterConfirmContent', $this->fieldLabel('AfterConfirmContent'), 5),
			new HeaderField('LimitedPlacesHeader', $this->fieldLabel('LimitedPlacesHeader')),
			new CheckboxField('LimitedPlaces', $this->fieldLabel('LimitedPlaces')),
			new NumericField('NumPlaces', $this->fieldLabel('NumPlaces')),
			new HeaderField('MultiplePlacesHeader', $this->fieldLabel('MultiplePlacesHeader')),
			new CheckboxField('MultiplePlaces', $this->fieldLabel('MultiplePlaces')),
			new NumericField('MaxPlaces', $this->fieldLabel('MaxPlaces')),
			new HeaderField('MemberSettingsHeader', $this->fieldLabel('MemberSettingsHeader')),
			new CheckboxField('RequireLoggedIn', $this->fieldLabel('RequireLoggedIn')),
			new CheckboxField('OneRegPerMember', $this->fieldLabel('OneRegPerMember'))
		));

		$fields->addFieldsToTab('Root.Content.AfterRegistration', array(
			new TextField('AfterRegTitle', $this->fieldLabel('AfterRegTitle')),
			new HtmlEditorField('AfterRegContent', $this->fieldLabel('AfterRegContent'))
		));

		// Only show the places column if multiple places are enabled.
		$regFields = singleton('EventRegistration')->summaryFields();
		if (!$this->MultiplePlaces) unset($regFields['Places']);

		$registrations = new ComplexTableField(
			$this, 'Registrations', 'EventRegistration', $regFields, null, '"Confirmed" = 1'
		);
		$registrations->setPermissions(array('show', 'print', 'export'));

		$fields->addFieldToTab('Root', new Tab('Registrations'), 'Behaviour');
		$fields->addFieldsToTab('Root.Registrations', array(
			new HeaderField('RegistrationsHeader', $this->fieldLabel('Registrations')),
			$registrations
		));

		if ($this->RegEmailConfirm) {
			$count = DB::query(sprintf(
				'SELECT COUNT(*) FROM "EventRegistration" WHERE "EventID" = %d AND "Confirmed" = 0',
				$this->ID
			));

			$unconfirmed = _t(
				'EventManagement.NUMUNCONFIRMEDREG',
				'There are %d unconfirmed registrations.');

			$fields->addFieldToTab('Root.Registrations', new LiteralField(
				'UnconfirmedRegistrations', sprintf("<p>$unconfirmed</p>", $count->value())
			));
		}

		return $fields;
	}

	public function fieldLabels() {
		return array_merge(parent::fieldLabels(), array(
			'Registrations' => _t('EventManagement.REGISTATIONS', 'Registrations'),
			'EmailConfirmationHeader' => _t('EventManagement.EMAILCONF', 'Email Confirmation'),
			'RegEmailConfirm' => _t('EventManagement.REQEMAILCONFIRM', 'Require email confirmation
				to complete registration?'),
			'AfterConfirmTitle' => _t('EventManagement.AFTERCONFIRMTITLE', 'After confirmation title'),
			'AfterConfirmContent' => _t('EventManagement.AFTERCONFIRMCONTENT', 'After confirmation content'),
			'LimitedPlacesHeader' => _t('EventManagement.LIMPLACES', 'Limited Places'),
			'LimitedPlaces' => _t('EventManagement.HASLIMPLACES', 'This event has limited places?'),
			'NumPlaces' => _t('EventManagement.NUMPLACESAVAILABLE', 'Number of places available'),
			'MultiplePlacesHeader' => _t('EventManagement.MULTIPLACES', 'Multiple Places'),
			'MultiplePlaces' => _t('EventManagement.ALLOWMULTIPLACES', 'Allow atendees to register for multiple places?'),
			'MaxPlaces' => _t('EventManagement.MAXPLACES', 'Maximum places selectable (0 for any number)'),
			'MemberSettingsHeader' => _t('EventManagement.MEMBERSETTINGS', 'Member Settings'),
			'RequireLoggedIn' => _t('EventManagement.REQUIREDLOGGEDIN', 'Require users to be logged in to register?'),
			'OneRegPerMember' => _t('EventMangement.LIMITMEMBERSTOSINGLEREG', 'Limit members to a single registration?'),
			'AfterRegTitle' => _t('EventManagement.AFTERREGTITLE', 'After registration title'),
			'AfterRegContent' => _t('EventManagement.AFTERREGCONTENT', 'After registration content')
		));
	}

}

/**
 * @package silverstripe-eventmanagement
 */
class RegisterableEvent_Controller extends CalendarEvent_Controller {

	public static $allowed_actions = array(
		'register',
		'unregister'
	);

	/**
	 * Returns the controller allowing a person to register for an event.
	 *
	 * @param  SS_HTTPRequest $request
	 * @return EventRegisterController
	 */
	public function register($request) {
		if (!$time = $this->getTimeById($request->param('ID'))) {
			$this->httpError(404, 'The requested event time could not be found.');
		}

		$request->shift(1);
		$request->shiftAllParams();

		return new EventRegisterController($this, $time);
	}

	/**
	 * Allows a person to remove their registration by entering their email
	 * address.
	 */
	public function unregister($request) {
		if (!$time = $this->getTimeById($request->param('ID'))) {
			$this->httpError(404, 'The requested event time could not be found.');
		}

		$request->shift(1);
		$request->shiftAllParams();

		return new EventUnregisterController($this, $time);
	}

	/**
	 * @param  int $id
	 * @return RegisterableDateTime
	 */
	protected function getTimeById($id) {
		$filter = sprintf(
			'"CalendarDateTime"."ID" = %d AND "EventID" = %d', $id, $this->ID
		);

		if ($time = $this->DateTimes($filter, null, null, 1)) {
			return $time->First();
		}
	}

}
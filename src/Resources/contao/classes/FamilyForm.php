<?php

/**
 * Contao Open Source CMS
 *
 * Copyright (c) 2005-2016 Leo Feyer
 *
 * @license LGPL-3.0+
 */

/**
 * Jmedia/AddressbookBundle
 *
 * @author Johannes Cram <johannes@jonesmedia.de>
 * @package AddressbookBundle
 * @license GPL-3.0+
 */

 /**
 * Provides methods to get the form fields to
 * update a tl_family record (if available, with
 * tl_member fields) as array or to create a
 * new tl_family record
 * Usage:
 *		//Get fields with prefilled data by tl_member id
 *		$form = new FamilyForm('update-with-member-id',$this->User->id);
 *		$arrFields = $form->getFormData();
 *
 *		//Get fields with prefilled data by tl_family id
 *		$form = new FamilyForm('update-with-family-id',$intId);
 *		$arrFields = $form->getFormData();
 *
 *		//Get empty fields for new tl_family record
 *		$form = new FamilyForm('new-record');
 *		$arrFields = $form->getFormData();
 *
 * 		//Write data, after creating objects
 *		$form->mixedData = $this->Input->post;
 *		$response = $form->save();
 */

 namespace Jmedia;

 class FamilyForm extends \System {

	 /**
	 * Setup displayed fields and sorting/grouping
	 * @var array
	 */
	 protected $arrFields = [
		'personal_legend' => [
			'firstname' => [],
			'lastname' => [],
			'nameOfBirth' => [],
			'gender' => [],
			'dateOfBirth' => [],
			'about_me' => []
	   ],
		'address_legend' => [
			'street' => [],
			'postal' => [],
			'city' => [],
			'country' => []
	   ],
		'contact_legend' => [
			'email' => [],
			'phone' => [],
			'mobile' => [],
			'fax'  => []
	   ],
		'family_legend' => [
			'mother' => [],
			'father' => [],
			'partner' => [],
			'partner_relation' => []
	   ]
	];

	/**
	* $arrFields with data
	* @var array
	*/
	protected $arrData;

	/**
	* Member Object
	* @var object
	*/
	protected $objMember = null;

	/**
	* Family record
	* @var array
	*/
	protected $arrFamily;

	/**
	* Modified Family record fields before validation
	* @var array
	*/
	protected $arrFamilyOld;

	/**
	* Contains list of keys that were modified
	* @var array
	*/
	protected $arrModified;

	/**
	* Type of loaded/new record
	* @var string
	* Options: update-with-member-id|update-with-family-id|new-record
	*/
	protected $strType;

	/**
	* Load member and family object if available and set type
	*
	* @param string $strType family|member|new
	* @param integer $intMemberId
	*/
	public function __construct($strType = 'new',$intId = 0) {
		\System::loadLanguageFile('tl_family');
		\System::loadLanguageFile('tl_member');

		if($strType == 'member' && $intId > 0) {
			$this->strType = 'update-with-member-id';
			$this->objMember = \MemberModel::findById($intId);
			$this->arrFamily = Family::getAddressEntryOfMember($intId);
		}
		elseif($strType == 'family' && $intId > 0) {
			$this->strType = 'update-with-family-id';
			$this->arrFamily = Family::getAddressEntry($intId);
		}
		elseif($strType == 'new'){
			$this->strType = 'new-record';
		}
		else {
			return false;
		}
	}

	/**
	* Get data, either from tl_family or tl_member record
	*
	* @param $strKey
	* @return mixed
	*/
	public function __get($strKey) {
		if(isset($this->arrFamily[$strKey])) {
			return $this->arrFamily[$strKey];
		}
		elseif(isset($this->objMember->strKey)) {
			return $this->objMember->strKey;
		}
		else{
			return false;
		}
	}

	/**
	* Set data, either to tl_family or tl_member
	*
	* @param $strKey
	* @param $data
	*/
	public function __set($strKey,$data) {
		if($strKey == 'mixedData') {
			foreach($data as $key => $value) {
				if ($key == 'email' || $key == 'about_me') {
					if($this->objMember->{$key} != $value) {
						$this->arrFamilyOld[$key] = $this->objMember->{$key};
						$this->objMember->{$key} = $value;
					}
				}
				elseif($this->getFieldGroup($key) && $this->arrFamily[$key] != $value) {
					$this->arrModified[] = $key;
					//save old value in $arrFamilyOld
					$this->arrFamilyOld[$key] = $this->arrFamily[$key];
					$this->arrFamily[$key] = $value;
				}
			}
		}
	}

	/**
	* Get form fields with data for the template
	*
	* @return array
	*/
	public function getFormData() {
		//copy field structure into $arrData
		$this->arrData = $this->arrFields;

		//add tl_member data if available
		if($this->strType == 'update-with-member-id') {
			$this->arrData[$this->getFieldGroup('email')]['email'] = [ 'value' => $this->objMember->email, 'type' => 'email', 'label' => $this->getLabel('email') ];
			$this->arrData[$this->getFieldGroup('about_me')]['about_me'] = [ 'value' => $this->objMember->about_me, 'type' => 'textarea', 'label' => $this->getLabel('about_me') ];
		}

		//add tl_family data
		foreach ($this->arrFamily as $clm => $val) {
			$arrOptions = [];
			$strClass = '';
			if($this->getFieldGroup($clm)) {
				//=== DATE OF BIRTH ===
				if($clm == 'dateOfBirth') {
					$val = date('d.m.Y',$val);
				}
				//=== POSTAL ===
				elseif($clm == 'postal') {
					$strType = 'number';
				}
				//=== TEL NUMBERS ===
				elseif(in_array($clm,['phone','mobile','fax'])) {
					$strType = 'tel';
				}
				//=== SELECT FIELDS ===
				elseif(in_array($clm,['gender','country','mother','father','partner','partner_relation'])) {
					$strType = 'select';
					$arrOptions = $this->getSelectOptions($clm);
					//add chosen to big select fields
					if(count($arrOptions) > 3) {
						$strClass = 'chosen';
					}
				}
				//=== ALL OTHER FIELDS ===
				else {
					$strType = 'text';
				}
				$this->arrData[$this->getFieldGroup($clm)][$clm] = [ 'value' =>$val, 'type' => $strType, 'label' => $this->getLabel($clm) ];
				$this->arrData[$this->getFieldGroup($clm)][$clm]['options'] = $arrOptions;
				$this->arrData[$this->getFieldGroup($clm)][$clm]['class'] = $strClass;
			}
		}
		return $this->arrData;
	}

	/**
	* Save form data
	*
	* @return mixed true|$arrErrors
	*/
	public function save() {
		//check dateOfBirth
		if($this->arrFamily['dateOfBirth'] && in_array('dateOfBirth',$this->arrModified)) {
			if(strlen(strval($this->arrFamily['dateOfBirth'])) == 9) {
				$blnValid = false;
			}
			else {
				$day = substr($this->arrFamily['dateOfBirth'],0,2);
				$month = substr($this->arrFamily['dateOfBirth'],3,2);
				$year = substr($this->arrFamily['dateOfBirth'],6,4);
				if(!is_numeric($day) || !is_numeric($month) || !is_numeric($year)) {
					$blnValid = false;
				}
				else {
					$blnIsDate = checkdate($month,$day,$year);
					$intTime = strtotime($year.'-'.$month.'-'.$day);
					if(!$blnIsDate || $intTime > time()) {
						$blnValid = false;
					}
					else {
						$blnValid = true;
						$this->arrFamily['dateOfBirth'] = $intTime;
					}
				}
			}
		}
		if(!$blnValid) {
			//error: reset to old value
			$this->arrFamily['dateOfBirth'] = $this->arrFamilyOld['dateOfBirth'];
			unset($this->arrModified['dateOfBirth']);
			return [ 'error' => 'dateOfBirth', 'label' => $GLOBALS['TL_LANG']['Family']['error']['dateOfBirth'] ];
		}

		//check postal
		if($this->arrFamily['postal'] && !is_numeric($this->arrFamily['postal'])) {
			//error: reset to old value
			$this->arrFamily['postal'] = $this->arrFamilyOld['postal'];
			unset($this->arrModified['postal']);
			return [ 'error' => 'postal', 'label' => $GLOBALS['TL_LANG']['Family']['error']['postal'] ];
		}
		//check phone/fax numbers
		$arrPhones = [ 'phone','mobile','fax' ];
		foreach($arrPhones as $elem) {
			if($this->arrFamily[$elem] && !preg_match('/^\+?([\d\s]+)$/', $this->arrFamily[$elem])){
				//error: reset to old value
				$this->arrFamily[$elem] = $this->arrFamilyOld[$elem];
				unset($this->arrModified[$elem]);
				return [ 'error' => $elem, 'label' => $GLOBALS['TL_LANG']['Family']['error']['phone'] ];
			}
		}
		//if update-with-member-id
		if($this->strType == 'update-with-member-id') {

			//check email
			if(!preg_match('/^\S+@\S+\.\w{2,}$/',$this->objMember->email)) {
				//error: reset to old value
				$this->objMember->email = $this->arrFamilyOld['email'];
				return [ 'error' => 'email', 'label' => $GLOBALS['TL_LANG']['Family']['error']['email'] ];
			}

			//update tl_member
			$this->objMember->save();

			//trigger save_callback on tl_member.email - is unfortunately not triggered on $objMember->save();
			\Controller::loadDataContainer('tl_member');
			$varValue = $this->objMember->email;
			if (is_array($GLOBALS['TL_DCA']['tl_member']['fields']['email']['save_callback'])) {
				foreach ($GLOBALS['TL_DCA']['tl_member']['fields']['email']['save_callback'] as $callback) {
					if (is_array($callback)) {
						$objCallback = static::importStatic($callback[0]);
						$varValue = $objCallback->{$callback[1]}($varValue, $this->objMember, $this);
					}
					elseif (is_callable($callback)) {
						$varValue = $callback($varValue, $this->objMember, $this);
					}
				}
			}
		}

		//update tl_family
		$arrFields = [];
		foreach($this->arrFamily as $key => $value) {
			if(in_array($key,$this->arrModified)) {
				$arrFields[$key] = $value;
			}
		}
		$arrFields['completed'] = 1;
		$db = \Database::getInstance();
		$db->prepare("UPDATE tl_family %s WHERE id = ?")
			->set($arrFields)->execute($this->arrFamily['id']);

		return true;
	}

	/**
	* Get labels for group headings
	*
	* @return array
	*/
	public function getLegendLabels() {
		foreach($this->arrFields as $strGroup => $fields) {
			$arrLabels[$strGroup] = $GLOBALS['TL_LANG']['tl_family'][$strGroup];
		}
		return $arrLabels;
	}

	/**
	* Get Options with label for select-fields
	*
	* @param $strClm
	* @return array
	*/
	protected function getSelectOptions($strClm) {
		//=== GENDER FIELD ===
		if($strClm == 'gender') {
			return [
				'' => '-',
				'male' => &$GLOBALS['TL_LANG']['MSC']['male'],
				'female' => &$GLOBALS['TL_LANG']['MSC']['female']
			];
		}
		//=== COUNTRY FIELD ===
		elseif($strClm == 'country') {
			return \System::getCountries();
		}
		//=== FAMILY FIELD ===
		elseif(in_array($strClm,['mother','father','partner'])){
			$arrOptions =  Family::nameList();
			//add empty option
			$arrOptions[0] = '-';
			//unset own entry from select options
			if($this->type == 'update-with-member-id' || $this->type == 'update-with-family-id') {
				unset($arrOptions[$this->arrFamily['id']]);
			}
			return $arrOptions;
		}
		//=== PARTNER RELATION FIELD ===
		elseif($strClm == 'partner_relation')  {
			return [
				'' => '-',
				'engaged' => &$GLOBALS['TL_LANG']['tl_family']['partner_relation_options']['engaged'],
				'married' => &$GLOBALS['TL_LANG']['tl_family']['partner_relation_options']['married'],
			];
		}
		//=== UNKNOWN FIELDS ===
		else return [];
	}

	/**
	* Get group name of field
	*
	* @param $strClm
	* @return string
	*/
	protected function getFieldGroup($strClm) {
		foreach($this->arrFields as $strGroup => $arrFields) {
			foreach($arrFields as $strField => $stuff) {
				if($strField == $strClm) {
					return $strGroup;
				}
			}
		}
		return false;
	}

	/**
	* Get field label from tl_family or tl_family language array
	*
	* @param $strClm
	* @return string
	*/
	protected function getLabel($strClm){
		if($strClm == 'email' || $strClm == 'about_me') {
			return $GLOBALS['TL_LANG']['tl_member'][$strClm][0];
		}
		else {
			return $GLOBALS['TL_LANG']['tl_family'][$strClm][0];
		}
	}
 }
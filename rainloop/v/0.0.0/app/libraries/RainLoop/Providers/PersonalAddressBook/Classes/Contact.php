<?php

namespace RainLoop\Providers\PersonalAddressBook\Classes;

use
	RainLoop\Providers\PersonalAddressBook\Enumerations\PropertyType,
	RainLoop\Providers\PersonalAddressBook\Classes\Property
;

class Contact
{
	/**
	 * @var string
	 */
	public $IdContact;

	/**
	 * @var string
	 */
	public $IdContactStr;

	/**
	 * @var string
	 */
	public $Display;

	/**
	 * @var int
	 */
	public $ScopeType;

	/**
	 * @var int
	 */
	public $Changed;

	/**
	 * @var int
	 */
	public $IdPropertyFromSearch;

	/**
	 * @var array
	 */
	public $Properties;

	/**
	 * @var array
	 */
	public $ReadOnly;

	/**
	 * @var string
	 */
	public $CardDavData;

	/**
	 * @var string
	 */
	public $CardDavHash;

	/**
	 * @var int
	 */
	public $CardDavSize;

	public function __construct()
	{
		$this->Clear();
	}

	public function Clear()
	{
		$this->IdContact = '';
		$this->IdContactStr = '';
		$this->IdUser = 0;
		$this->Display = '';
		$this->ScopeType = \RainLoop\Providers\PersonalAddressBook\Enumerations\ScopeType::DEFAULT_;
		$this->Changed = \time();
		$this->IdPropertyFromSearch = 0;
		$this->Properties = array();
		$this->ReadOnly = false;

		$this->CardDavData = '';
		$this->CardDavHash = '';
		$this->CardDavSize = 0;
	}
	
	public function UpdateDependentValues($bReparseVcard = true)
	{
		$sLastName = '';
		$sFirstName = '';
		$sEmail = '';
		$sOther = '';

		$oFullNameProperty = null;
		
		foreach ($this->Properties as /* @var $oProperty \RainLoop\Providers\PersonalAddressBook\Classes\Property */ &$oProperty)
		{
			if ($oProperty)
			{
				$oProperty->ScopeType = $this->ScopeType;
				$oProperty->UpdateDependentValues();

				if (!$oFullNameProperty && PropertyType::FULLNAME === $oProperty->Type)
				{
					$oFullNameProperty =& $oProperty;
				}

				if (0 < \strlen($oProperty->Value))
				{
					if ('' === $sEmail && $oProperty->IsEmail())
					{
						$sEmail = $oProperty->Value;
					}
					else if ('' === $sLastName && PropertyType::LAST_NAME === $oProperty->Type)
					{
						$sLastName = $oProperty->Value;
					}
					else if ('' === $sFirstName && PropertyType::FIRST_NAME === $oProperty->Type)
					{
						$sFirstName = $oProperty->Value;
					}
					else if (\in_array($oProperty->Type, array(PropertyType::FULLNAME,
						PropertyType::PHONE_PERSONAL, PropertyType::PHONE_BUSSINES,
						PropertyType::MOBILE_PERSONAL, PropertyType::MOBILE_BUSSINES,
					)))
					{
						$sOther = $oProperty->Value;
					}
				}
			}
		}

		if (empty($this->IdContactStr))
		{
			$this->IdContactStr = \Sabre\DAV\UUIDUtil::getUUID();
		}

		$sDisplay = '';
		if (0 < \strlen($sLastName) || 0 < \strlen($sFirstName))
		{
			$sDisplay = \trim($sLastName.' '.$sFirstName);
		}

		if ('' === $sDisplay && 0 < \strlen($sEmail))
		{
			$sDisplay = \trim($sEmail);
		}

		if ('' === $sDisplay)
		{
			$sDisplay = $sOther;
		}

		$this->Display = \trim($sDisplay);

		$bNewFull = false;
		if (!$oFullNameProperty)
		{
			$oFullNameProperty = new \RainLoop\Providers\PersonalAddressBook\Classes\Property(PropertyType::FULLNAME, $this->Display);
			$bNewFull = true;
		}

		$oFullNameProperty->Value = $this->Display;
		$oFullNameProperty->UpdateDependentValues();

		if ($bNewFull)
		{
			$this->Properties[] = $oFullNameProperty;
		}

		if ($bReparseVcard || '' === $this->CardDavData)
		{
			$oVCard = $this->ToVCardObject($this->CardDavData);
			$this->CardDavData = $oVCard ? $oVCard->serialize() : $this->CardDavData;
		}

		if (!empty($this->CardDavData))
		{
			$this->CardDavHash = \md5($this->CardDavData);
			$this->CardDavSize = \strlen($this->CardDavData);
		}
		else
		{
			$this->CardDavHash = '';
			$this->CardDavSize = 0;
		}
	}

	/**
	 * @return array
	 */
	public function GetEmails()
	{
		$aResult = array();
		foreach ($this->Properties as /* @var $oProperty \RainLoop\Providers\PersonalAddressBook\Classes\Property */ &$oProperty)
		{
			if ($oProperty && $oProperty->IsEmail())
			{
				$aResult[] = $oProperty->Value;
			}
		}

		return \array_unique($aResult);
	}

	/**
	 * @param \Sabre\VObject\Document $oVCard
	 * @param string $sCardData
	 */
	public function ParseVCard($oVCard, $sVCard)
	{
		$bNew = empty($this->IdContact);

		if (!$bNew)
		{
			$this->Properties = array();
		}

		$aProperties = array();
		if ($oVCard && $oVCard->UID)
		{
			$this->IdContactStr = (string) $oVCard->UID;

			if (isset($oVCard->FN) && '' !== \trim($oVCard->FN))
			{
				$aProperties[] = new Property(PropertyType::FULLNAME, \trim($oVCard->FN));
			}

			if (isset($oVCard->NICKNAME) && '' !== \trim($oVCard->NICKNAME))
			{
				$aProperties[] = new Property(PropertyType::NICK_NAME, \trim($oVCard->NICKNAME));
			}

//			if (isset($oVCard->NOTE) && '' !== \trim($oVCard->NOTE))
//			{
//				$aProperties[] = new Property(PropertyType::NOTE, \trim($oVCard->NOTE));
//			}

			if (isset($oVCard->N))
			{
				$aNames = $oVCard->N->getParts();
				foreach ($aNames as $iIndex => $sValue)
				{
					$sValue = \trim($sValue);
					switch ($iIndex) {
						case 0:
							$aProperties[] = new Property(PropertyType::LAST_NAME, $sValue);
							break;
						case 1:
							$aProperties[] = new Property(PropertyType::FIRST_NAME, $sValue);
							break;
						case 2:
							$aProperties[] = new Property(PropertyType::MIDDLE_NAME, $sValue);
							break;
						case 3:
							$aProperties[] = new Property(PropertyType::NAME_PREFIX, $sValue);
							break;
						case 4:
							$aProperties[] = new Property(PropertyType::NAME_SUFFIX, $sValue);
							break;
					}
				}
			}

			if (isset($oVCard->EMAIL))
			{
				$bPref = false;
				foreach($oVCard->EMAIL as $oEmail)
				{
					$oTypes = $oEmail ? $oEmail['TYPE'] : null;
					$sEmail = $oTypes ? \trim((string) $oEmail) : '';

					if ($oTypes && 0 < \strlen($sEmail))
					{
						$oProp = new Property($oTypes->has('WORK') ? PropertyType::EMAIl_BUSSINES : PropertyType::EMAIl_PERSONAL, $sEmail);
						if (!$bPref && $oTypes->has('pref'))
						{
							$bPref = true;
							\array_unshift($aProperties, $oProp);
						}
						else
						{
							\array_push($aProperties, $oProp);
						}
					}
				}
			}
			
			if (isset($oVCard->TEL))
			{
				$bPref = false;
				foreach($oVCard->TEL as $oTel)
				{
					$oTypes = $oTel ? $oTel['TYPE'] : null;
					$sTel = $oTypes ? \trim((string) $oTel) : '';

					if ($oTypes && 0 < \strlen($sTel))
					{
						$oProp = null;
						$bWork = $oTypes->has('WORK');
						
						switch (true)
						{
							case $oTypes->has('VOICE'):
								$oProp = new Property($bWork ? PropertyType::PHONE_BUSSINES : PropertyType::PHONE_PERSONAL, $sTel);
								break;
							case $oTypes->has('CELL'):
								$oProp = new Property($bWork ? PropertyType::MOBILE_BUSSINES : PropertyType::MOBILE_PERSONAL, $sTel);
								break;
							case $oTypes->has('FAX'):
								$oProp = new Property($bWork ? PropertyType::FAX_BUSSINES : PropertyType::FAX_PERSONAL, $sTel);
								break;
							case $oTypes->has('WORK'):
								$oProp = new Property(PropertyType::MOBILE_BUSSINES, $sTel);
								break;
							default:
								$oProp = new Property(PropertyType::MOBILE_PERSONAL, $sTel);
								break;
						}

						if ($oProp)
						{
							if (!$bPref && $oTypes->has('pref'))
							{
								$bPref = true;
								\array_unshift($aProperties, $oProp);
							}
							else
							{
								\array_push($aProperties, $oProp);
							}
						}
					}
				}
			}
			
			$this->Properties = $aProperties;

			$this->CardDavData = $sVCard;
		}

		$this->UpdateDependentValues(false);
	}

	/**
	 * @return string
	 */
	public function ToVCardObject($sPreVCard = '')
	{
//		$this->UpdateDependentValues();

		$oVCard = null;
		if (0 < \strlen($sPreVCard))
		{
			try
			{
				$oVCard = \Sabre\VObject\Reader::read($sPreVCard);
			}
			catch (\Exception $oExc) {};
		}

		if (!$oVCard)
		{
			$oVCard = new \Sabre\VObject\Component\VCard();
		}

		$oVCard->VERSION = '3.0';
		$oVCard->PRODID = '-//RainLoop//'.APP_VERSION.'//EN';

		unset($oVCard->FN, $oVCard->EMAIL, $oVCard->TEL);

		$bPrefEmail = $bPrefPhone = false;
		$sFirstName = $sLastName = $sMiddleName = $sSuffix = $sPrefix = '';
		foreach ($this->Properties as /* @var $oProperty \RainLoop\Providers\PersonalAddressBook\Classes\Property */ &$oProperty)
		{
			if ($oProperty)
			{
				switch ($oProperty->Type)
				{
					case PropertyType::FULLNAME:
						$oVCard->FN = $oProperty->Value;
						break;
					case PropertyType::NICK_NAME:
						$oVCard->NICKNAME = $oProperty->Value;
						break;
					case PropertyType::FIRST_NAME:
						$sFirstName = $oProperty->Value;
						break;
					case PropertyType::LAST_NAME:
						$sLastName = $oProperty->Value;
						break;
					case PropertyType::MIDDLE_NAME:
						$sMiddleName = $oProperty->Value;
						break;
					case PropertyType::NAME_SUFFIX:
						$sSuffix = $oProperty->Value;
						break;
					case PropertyType::NAME_PREFIX:
						$sPrefix = $oProperty->Value;
						break;
					case PropertyType::EMAIl_PERSONAL:
					case PropertyType::EMAIl_BUSSINES:
						$aParams = array('TYPE' => array('INTERNET'));
						$aParams['TYPE'][] = PropertyType::EMAIl_BUSSINES === $oProperty->Type ? 'WORK' : 'HOME';

						if (!$bPrefEmail)
						{
							$bPrefEmail = true;
							$aParams['TYPE'][] = 'pref';
						}
						$oVCard->add('EMAIL', $oProperty->Value, $aParams);
						break;
					case PropertyType::PHONE_PERSONAL:
					case PropertyType::PHONE_BUSSINES:
					case PropertyType::MOBILE_PERSONAL:
					case PropertyType::MOBILE_BUSSINES:
					case PropertyType::FAX_PERSONAL:
					case PropertyType::FAX_BUSSINES:
						$aParams = array('TYPE' => array());
						$sType = '';
						if (\in_array($oProperty->Type, array(PropertyType::PHONE_PERSONAL, PropertyType::PHONE_BUSSINES)))
						{
							$sType = 'VOICE';
						}
						else if (\in_array($oProperty->Type, array(PropertyType::MOBILE_PERSONAL, PropertyType::MOBILE_BUSSINES)))
						{
							$sType = 'CELL';
						}
						else if (\in_array($oProperty->Type, array(PropertyType::FAX_PERSONAL, PropertyType::FAX_BUSSINES)))
						{
							$sType = 'FAX';
						}

						if (!empty($sType))
						{
							$aParams['TYPE'][] = $sType;
						}

						$aParams['TYPE'][] = \in_array($oProperty->Type, array(
							PropertyType::PHONE_BUSSINES, PropertyType::MOBILE_BUSSINES, PropertyType::FAX_BUSSINES))  ? 'WORK' : 'HOME';

						if (!$bPrefPhone)
						{
							$bPrefPhone = true;
							$aParams['TYPE'][] = 'pref';
						}

						$oVCard->add('TEL', $oProperty->Value, $aParams);
						break;
				}
			}
		}

		$oVCard->UID = $this->IdContactStr;
		$oVCard->N = array($sLastName, $sFirstName, $sMiddleName, $sPrefix, $sSuffix);
		$oVCard->REV = \gmdate('Ymd', $this->Changed).'T'.\gmdate('His', $this->Changed).'Z';

		return $oVCard;
	}

	/**
	 * @return string
	 */
	public function VCardUri()
	{
		return $this->IdContactStr.'.vcf';
	}
}

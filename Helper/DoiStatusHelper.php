<?php
namespace MauticPlugin\IccDoiBundle\Helper;

use Doctrine\ORM\ORMException;
use Mautic\EmailBundle\Entity\Email;
use Mautic\EmailBundle\Model\EmailModel;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\LeadBundle\Entity\LeadListRepository;
use Mautic\LeadBundle\Model\LeadModel;
use Mautic\LeadBundle\Model\ListModel;
use Mautic\PluginBundle\Helper\IntegrationHelper;
use Mautic\UserBundle\Model\UserModel;
use MauticPlugin\IccDoiBundle\Entity\LeadDoi;
use MauticPlugin\IccDoiBundle\Event\DoiChangeEvent;
use MauticPlugin\IccDoiBundle\IccDoiEvents;
use MauticPlugin\IccDoiBundle\IccDoiStatus;
use MauticPlugin\IccDoiBundle\IccDoiUrls;
use MauticPlugin\IccDoiBundle\Integration\IccDoiIntegration;
use MauticPlugin\IccDoiBundle\Model\LeadDoiModel;
//use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class DoiStatusHelper {

    /**
     * @var IccDoiIntegration
     */
    private $integration;

    /**
     * @var LeadListRepository
     */
    private $leadListRepository;

    /**
     * @var EventDispatcher
     */
    private $eventDispatcher;

    /**
     * @var Email
     */
    private $doiEmail;


    /**
     * @var EmailModel
     */
    private $emailModel;

    /**
     * @var ListModel
     */
    private $listModel;

    /**
     * @var LeadModel
     */
    private $leadModel;

    /**
     * @var LeadDoiModel
     */
    private $leadDoiModel;

    public function __construct(IntegrationHelper $integrationHelper,
                                LeadListRepository $leadListRepository,
                                EventDispatcherInterface $eventDispatcher,
                                EmailModel $emailModel,
                                ListModel $listModel,
                                LeadModel $leadModel,
                                LeadDoiModel $leadDoiModel)
    {
        $this->integration = $integrationHelper->getIntegrationObject('IccDoi');
        $this->leadListRepository = $leadListRepository;
        $this->eventDispatcher = $eventDispatcher;
        $this->emailModel = $emailModel;
        $this->listModel = $listModel;

        $featureSettings = $this->integration->getIntegrationSettings()->getFeatureSettings();

        $this->doiEmail = !empty($featureSettings['doi_email'][0])
            ? $emailModel->getEntity((int) $featureSettings['doi_email'][0])
            : null;
        $this->leadModel = $leadModel;
        $this->leadDoiModel = $leadDoiModel;
    }

    /**
     * Checks Doi Segments and returns current doi status of lead
     *
     * @param Lead $lead
     * @return string
     */
    public function getCurrentDoiStatus(Lead $lead)
    {
        /** @var LeadDoi $leadDoi */
        $leadDoi = $this->leadDoiModel->getEntity($lead->getId());

        if ($leadDoi == NULL)
            return IccDoiStatus::DOI_NONE;

        return $leadDoi->getDoiStatus();
    }

    /**
     * Checks if current page hit is doi confirmation page
     *
     * @param Lead $lead
     * @param $url
     * @return bool
     */
    public function isDoiTargetPage(Lead $lead, $url) {
        /** @var LeadDoi $leadDoi */
        $leadDoi = $this->leadDoiModel->getEntity($lead->getId());

        if ($leadDoi === null)
            return false;

        if ($leadDoi->getDoiStatus() == IccDoiStatus::DOI_CONFIRMED)
            return false;

        $url_components = parse_url($url);
        if (!isset($url_components['query'])) {
            return false;
        }
        parse_str($url_components['query'], $params);

        if (isset($params['a']))
            if ($params['a'] == $leadDoi->getActivationLink())
                return true;

        return false;
    }

    /**
     * Checks if current page hit is doi unsubscribe page
     *
     * @param Lead $lead
     * @param $url
     * @return bool
     */
    public function isDoiUnsubscribePage(Lead $lead, $url) {
        /** @var LeadDoi $leadDoi */
        $leadDoi = $this->leadDoiModel->getEntity($lead->getId());
        if ($leadDoi === null)
            return false;

        if ($leadDoi->getDoiStatus() == IccDoiStatus::DOI_OPTED_OUT)
            return false;


        if (in_array($url, IccDoiUrls::UNSUBSCRIBE_URLS))
            return true;

        return false;
    }

    /**
     * Sets current doi status of lead
     *
     * @param Lead $lead
     * @param $doiStatus
     * @throws ORMException
     */
    public function setDoiStatus(Lead $lead, $doiStatus)
    {
        /** @var LeadDoi $leadDoi */
        $leadDoi = $this->leadDoiModel->getEntity($lead->getId());

        $dateTime = new \DateTime('now');

        $leadDoi->setDoiStatus($doiStatus);
        if ($doiStatus == IccDoiStatus::DOI_CONFIRMED) {
            $leadDoi->setDoiAcceptedDate($dateTime);
            $this->sendTypeMail($lead);
        }
        if ($doiStatus == IccDoiStatus::DOI_OPTED_OUT)
            $leadDoi->setDoiDeclinedDate($dateTime);

        $this->leadDoiModel->save($leadDoi);

        $doiChangeEvent = New DoiChangeEvent($lead, $doiStatus);
        $this->eventDispatcher->dispatch($doiChangeEvent, IccDoiEvents::ON_DOI_STATUS_CHANGE);
//        $this->eventDispatcher->dispatch(IccDoiEvents::ON_DOI_STATUS_CHANGE, $doiChangeEvent);
    }

    /**
     * sendTypeMail: Send Email to user to define email type of lead
     * insignio HG 03.08.23
     * @param Lead $lead
     * @return bool
     */
    protected function sendTypeMail(Lead $lead): bool {
        $featureSettings = $this->integration->getIntegrationSettings()->getFeatureSettings();
        $email = $this->emailModel->getEntity((int)$featureSettings['doi_email_type_mail']);

        $this->emailModel->sendEmailToUser($email, (int)$featureSettings['doi_email_type_receiver'], $lead->convertToArray());
        return true;
    }

    public function isDoiEmail($email) {
        if(!$email) {
            return false;
        }
        $emailId = $email->getId();
        if($translatedEmail = $email->getTranslationParent()){
            $emailId = $translatedEmail->getId();
        }

        $featureSettings = $this->integration->getIntegrationSettings()->getFeatureSettings();

        return (int)$featureSettings['doi_email'] === $emailId;
    }

    /**
     * Sends doi email to lead
     *
     * @param $lead
     * @return array|bool|mixed
     * @throws ORMException
     */
    public function sendDoiEmail(Lead $lead)
    {
        $featureSettings = $this->integration->getIntegrationSettings()->getFeatureSettings();

        $doiEmail = $this->emailModel->getEntity((int)$featureSettings['doi_email']);

        [$translationParent, $translatedEntity] = $this->emailModel->getTranslatedEntity($doiEmail, $lead);

        return $this->emailModel->sendEmail($translatedEntity, $lead->convertToArray());
    }

    public function buildActivationLink($lead) {
        if ($lead instanceof Lead) {
            $leadId = $lead->getId();
            $language = $lead->getPreferredLocale();
        } elseif (is_array($lead)) {
            $leadId = $lead['id'];
            $lead = $this->leadModel->getEntity($leadId);
            $language = $lead->getPreferredLocale();
        }

        if (!empty($language) && isset(IccDoiUrls::ACTIVATION_URLS[$language]))
            $activationUrl = IccDoiUrls::ACTIVATION_URLS[$language];
        else {
            $activationUrl = IccDoiUrls::ACTIVATION_URLS['de'];
        }

        $leadDoi = (empty($leadId)) ? NULL : $this->leadDoiModel->getEntity($leadId);

        if ($leadDoi == NULL) {
            $leadDoi = new LeadDoi($lead);
        }

        $activationHash = $this->generateRandomString();
        $activationUrl .= '?a=' . $activationHash;

        $leadDoi->setActivationLink($activationHash);
        $leadDoi->setDoiStatus(IccDoiStatus::DOI_PENDING);
        $this->leadDoiModel->save($leadDoi, false);

        return $activationUrl;
    }

    public function buildUnsubscribeLink($lead) {
        if ($lead instanceof Lead) {
            $language = $lead->getPreferredLocale();
        } elseif (is_array($lead)) {
            $leadId = $lead['id'];
            $lead = $this->leadModel->getEntity($leadId);
            $language = $lead->getPreferredLocale();
        }

        if (isset(IccDoiUrls::UNSUBSCRIBE_URLS[$language]))
            $unsubscribeUrl = IccDoiUrls::UNSUBSCRIBE_URLS[$language];
        else
            $unsubscribeUrl = IccDoiUrls::UNSUBSCRIBE_URLS['de'];

        return $unsubscribeUrl;
    }

    public function buildUnsubscribeFormLink($lead) {
        if ($lead instanceof Lead) {
            $language = $lead->getPreferredLocale();
        } elseif (is_array($lead)) {
            $leadId = $lead['id'];
            $lead = $this->leadModel->getEntity($leadId);
            $language = $lead->getPreferredLocale();
        }

        if (isset(IccDoiUrls::UNSUBSCRIBE_FORM_URLS[$language]))
            $unsubscribeUrl = IccDoiUrls::UNSUBSCRIBE_FORM_URLS[$language];
        else
            $unsubscribeUrl = IccDoiUrls::UNSUBSCRIBE_FORM_URLS['de'];

        return $unsubscribeUrl;
    }

    private function generateRandomString($length = 10) {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }

    private function getCountryCodeByCountry($country) {

        $countryList = array(
            'AF' => 'Afghanistan',
            'AX' => 'Aland Islands',
            'AL' => 'Albania',
            'DZ' => 'Algeria',
            'AS' => 'American Samoa',
            'AD' => 'Andorra',
            'AO' => 'Angola',
            'AI' => 'Anguilla',
            'AQ' => 'Antarctica',
            'AG' => 'Antigua and Barbuda',
            'AR' => 'Argentina',
            'AM' => 'Armenia',
            'AW' => 'Aruba',
            'AU' => 'Australia',
            'AT' => 'Austria',
            'AZ' => 'Azerbaijan',
            'BS' => 'Bahamas the',
            'BH' => 'Bahrain',
            'BD' => 'Bangladesh',
            'BB' => 'Barbados',
            'BY' => 'Belarus',
            'BE' => 'Belgium',
            'BZ' => 'Belize',
            'BJ' => 'Benin',
            'BM' => 'Bermuda',
            'BT' => 'Bhutan',
            'BO' => 'Bolivia',
            'BA' => 'Bosnia and Herzegovina',
            'BW' => 'Botswana',
            'BV' => 'Bouvet Island (Bouvetoya)',
            'BR' => 'Brazil',
            'IO' => 'British Indian Ocean Territory (Chagos Archipelago)',
            'VG' => 'British Virgin Islands',
            'BN' => 'Brunei Darussalam',
            'BG' => 'Bulgaria',
            'BF' => 'Burkina Faso',
            'BI' => 'Burundi',
            'KH' => 'Cambodia',
            'CM' => 'Cameroon',
            'CA' => 'Canada',
            'CV' => 'Cape Verde',
            'KY' => 'Cayman Islands',
            'CF' => 'Central African Republic',
            'TD' => 'Chad',
            'CL' => 'Chile',
            'CN' => 'China',
            'CX' => 'Christmas Island',
            'CC' => 'Cocos (Keeling) Islands',
            'CO' => 'Colombia',
            'KM' => 'Comoros the',
            'CD' => 'Congo',
            'CG' => 'Congo the',
            'CK' => 'Cook Islands',
            'CR' => 'Costa Rica',
            'CI' => 'Cote d\'Ivoire',
            'HR' => 'Croatia',
            'CU' => 'Cuba',
            'CY' => 'Cyprus',
            'CZ' => 'Czech Republic',
            'DK' => 'Denmark',
            'DJ' => 'Djibouti',
            'DM' => 'Dominica',
            'DO' => 'Dominican Republic',
            'EC' => 'Ecuador',
            'EG' => 'Egypt',
            'SV' => 'El Salvador',
            'GQ' => 'Equatorial Guinea',
            'ER' => 'Eritrea',
            'EE' => 'Estonia',
            'ET' => 'Ethiopia',
            'FO' => 'Faroe Islands',
            'FK' => 'Falkland Islands (Malvinas)',
            'FJ' => 'Fiji the Fiji Islands',
            'FI' => 'Finland',
            'FR' => 'France, French Republic',
            'GF' => 'French Guiana',
            'PF' => 'French Polynesia',
            'TF' => 'French Southern Territories',
            'GA' => 'Gabon',
            'GM' => 'Gambia the',
            'GE' => 'Georgia',
            'DE' => 'Germany',
            'GH' => 'Ghana',
            'GI' => 'Gibraltar',
            'GR' => 'Greece',
            'GL' => 'Greenland',
            'GD' => 'Grenada',
            'GP' => 'Guadeloupe',
            'GU' => 'Guam',
            'GT' => 'Guatemala',
            'GG' => 'Guernsey',
            'GN' => 'Guinea',
            'GW' => 'Guinea-Bissau',
            'GY' => 'Guyana',
            'HT' => 'Haiti',
            'HM' => 'Heard Island and McDonald Islands',
            'VA' => 'Holy See (Vatican City State)',
            'HN' => 'Honduras',
            'HK' => 'Hong Kong',
            'HU' => 'Hungary',
            'IS' => 'Iceland',
            'IN' => 'India',
            'ID' => 'Indonesia',
            'IR' => 'Iran',
            'IQ' => 'Iraq',
            'IE' => 'Ireland',
            'IM' => 'Isle of Man',
            'IL' => 'Israel',
            'IT' => 'Italy',
            'JM' => 'Jamaica',
            'JP' => 'Japan',
            'JE' => 'Jersey',
            'JO' => 'Jordan',
            'KZ' => 'Kazakhstan',
            'KE' => 'Kenya',
            'KI' => 'Kiribati',
            'KP' => 'Korea',
            'KR' => 'Korea',
            'KW' => 'Kuwait',
            'KG' => 'Kyrgyz Republic',
            'LA' => 'Lao',
            'LV' => 'Latvia',
            'LB' => 'Lebanon',
            'LS' => 'Lesotho',
            'LR' => 'Liberia',
            'LY' => 'Libyan Arab Jamahiriya',
            'LI' => 'Liechtenstein',
            'LT' => 'Lithuania',
            'LU' => 'Luxembourg',
            'MO' => 'Macao',
            'MK' => 'Macedonia',
            'MG' => 'Madagascar',
            'MW' => 'Malawi',
            'MY' => 'Malaysia',
            'MV' => 'Maldives',
            'ML' => 'Mali',
            'MT' => 'Malta',
            'MH' => 'Marshall Islands',
            'MQ' => 'Martinique',
            'MR' => 'Mauritania',
            'MU' => 'Mauritius',
            'YT' => 'Mayotte',
            'MX' => 'Mexico',
            'FM' => 'Micronesia',
            'MD' => 'Moldova',
            'MC' => 'Monaco',
            'MN' => 'Mongolia',
            'ME' => 'Montenegro',
            'MS' => 'Montserrat',
            'MA' => 'Morocco',
            'MZ' => 'Mozambique',
            'MM' => 'Myanmar',
            'NA' => 'Namibia',
            'NR' => 'Nauru',
            'NP' => 'Nepal',
            'AN' => 'Netherlands Antilles',
            'NL' => 'Netherlands the',
            'NC' => 'New Caledonia',
            'NZ' => 'New Zealand',
            'NI' => 'Nicaragua',
            'NE' => 'Niger',
            'NG' => 'Nigeria',
            'NU' => 'Niue',
            'NF' => 'Norfolk Island',
            'MP' => 'Northern Mariana Islands',
            'NO' => 'Norway',
            'OM' => 'Oman',
            'PK' => 'Pakistan',
            'PW' => 'Palau',
            'PS' => 'Palestinian Territory',
            'PA' => 'Panama',
            'PG' => 'Papua New Guinea',
            'PY' => 'Paraguay',
            'PE' => 'Peru',
            'PH' => 'Philippines',
            'PN' => 'Pitcairn Islands',
            'PL' => 'Poland',
            'PT' => 'Portugal, Portuguese Republic',
            'PR' => 'Puerto Rico',
            'QA' => 'Qatar',
            'RE' => 'Reunion',
            'RO' => 'Romania',
            'RU' => 'Russian Federation',
            'RW' => 'Rwanda',
            'BL' => 'Saint Barthelemy',
            'SH' => 'Saint Helena',
            'KN' => 'Saint Kitts and Nevis',
            'LC' => 'Saint Lucia',
            'MF' => 'Saint Martin',
            'PM' => 'Saint Pierre and Miquelon',
            'VC' => 'Saint Vincent and the Grenadines',
            'WS' => 'Samoa',
            'SM' => 'San Marino',
            'ST' => 'Sao Tome and Principe',
            'SA' => 'Saudi Arabia',
            'SN' => 'Senegal',
            'RS' => 'Serbia',
            'SC' => 'Seychelles',
            'SL' => 'Sierra Leone',
            'SG' => 'Singapore',
            'SK' => 'Slovakia (Slovak Republic)',
            'SI' => 'Slovenia',
            'SB' => 'Solomon Islands',
            'SO' => 'Somalia, Somali Republic',
            'ZA' => 'South Africa',
            'GS' => 'South Georgia and the South Sandwich Islands',
            'ES' => 'Spain',
            'LK' => 'Sri Lanka',
            'SD' => 'Sudan',
            'SR' => 'Suriname',
            'SJ' => 'Svalbard & Jan Mayen Islands',
            'SZ' => 'Swaziland',
            'SE' => 'Sweden',
            'CH' => 'Switzerland, Swiss Confederation',
            'SY' => 'Syrian Arab Republic',
            'TW' => 'Taiwan',
            'TJ' => 'Tajikistan',
            'TZ' => 'Tanzania',
            'TH' => 'Thailand',
            'TL' => 'Timor-Leste',
            'TG' => 'Togo',
            'TK' => 'Tokelau',
            'TO' => 'Tonga',
            'TT' => 'Trinidad and Tobago',
            'TN' => 'Tunisia',
            'TR' => 'Turkey',
            'TM' => 'Turkmenistan',
            'TC' => 'Turks and Caicos Islands',
            'TV' => 'Tuvalu',
            'UG' => 'Uganda',
            'UA' => 'Ukraine',
            'AE' => 'United Arab Emirates',
            'GB' => 'United Kingdom',
            'US' => 'USA',
            'UM' => 'United States Minor Outlying Islands',
            'VI' => 'United States Virgin Islands',
            'UY' => 'Uruguay, Eastern Republic of',
            'UZ' => 'Uzbekistan',
            'VU' => 'Vanuatu',
            'VE' => 'Venezuela',
            'VN' => 'Vietnam',
            'WF' => 'Wallis and Futuna',
            'EH' => 'Western Sahara',
            'YE' => 'Yemen',
            'ZM' => 'Zambia',
            'ZW' => 'Zimbabwe'
        );

        $key = array_search($country, $countryList);

        return $key;
    }

    /**
     * getFeatureSetting: return value for given setting name
     * insignio HG 04.08.23
     * @param string $settingName
     * @return mixed|null
     */
    public function getFeatureSetting(string $settingName) {
        $featureSettings = $this->integration->getIntegrationSettings()->getFeatureSettings();
        $setting = $featureSettings[$settingName];

        return (empty($setting)) ? NULL : $setting;
    }

    public function buildTypeMeta($lead): string
    {
        if ($lead instanceof Lead) {
            $leadId = $lead->getId();
        } elseif (is_array($lead)) {
            $leadId = $lead['id'];
        }

        $leadDoi = $this->leadDoiModel->getEntity($leadId);

        $meta = [];
        $meta['leadId'] = $leadId;
        $meta['token'] = uniqid() . '_' . time();
        $jsonMeta = base64_encode(json_encode($meta));

        $leadDoi->setToken($meta['token']);
        $this->leadDoiModel->save($leadDoi);

        return $jsonMeta;
    }

    public function setDoiType($leadId, $token, $type): bool {
        $leadDoi = $this->leadDoiModel->getEntity($leadId);
        if (!$leadDoi) {
            return false;
        }
        $lead = $this->leadModel->getEntity($leadId);
        $leadToken = $leadDoi->getToken();

        switch($type) {
            case 'doi_email_type_prices':
                $doiType = "prices";
                break;
            case 'doi_email_type_press':
                $doiType = "press";
                break;
            case 'doi_email_type_public':
                $doiType = "public";
                break;
            default:
                return false;
        }


        if(!empty($leadToken)) {
            if ($token == $leadToken) {
                $data = [
                    'icc_doi_type' => [
                        'value' => $doiType
                    ]
                ];
                $this->leadModel->setFieldValues($lead, $data);
                $this->leadModel->save($lead);
                $leadDoi->setToken("");

                $this->leadDoiModel->save($leadDoi);

                return true;
            }
        }

        return false;
    }

    public function getLeadEntityByID($leadId) {
        return $this->leadModel->getEntity($leadId);
    }
}

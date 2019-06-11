<?php

namespace common\models;

use PDO;
use Yii;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;
use yii\helpers\ArrayHelper;
use yii\helpers\HtmlPurifier;
use yii\helpers\Json;
use yii\validators\Validator;

/**
 * This is the model class for table "Settings".
 *
 * @property integer $id
 * @property integer $to
 * @property integer $currency_prefer
 * @property integer $count_month_graph
 * @property integer $time_zone
 * @property integer $maintenance_message_time_to
 * @property integer $maintenance_message_time_to_mobile
 * @property integer $campaign_id
 * @property integer $admin_or_support_email_maintenance
 * @property integer $maintenance_on_off
 * @property integer $maintenance_on_off_mobile
 * @property integer $switch_delete
 * @property integer $maintenance_message_time_show
 * @property float $lng_contact
 * @property float $lat_contact
 * @property float $address_other_info
 * @property string $url_list_currency
 * @property string $address_call_center
 * @property string $address_city_region
 * @property string $address_zip
 * @property string $address_number_street
 * @property string $address_geo_title
 * @property string $address_country
 * @property string $title
 * @property string $license
 * @property string $url_site
 * @property string $url_site_admin
 * @property string $description_offer
 * @property string $description_job_offer
 * @property string $description_traineeships_offer
 * @property string $maintenance_message_main
 * @property string $maintenance_message_about
 * @property string $maintenance_message_about_mobile
 * @property integer $country
 * @property integer $city
 * @property boolean $switch_show_places
 * @property object $address_geo
 *
 *
 * @property array $editorIps
 * @property array $maintenanceIps
 * @property array $editorIsoCountry
 */

class Settings extends ActiveRecord{

    const DESC = 1;
    const ASC = 0;

    const TEST = 1;
    const LIVE = 0;

    const EN = 0;
    const FR = 1;

    const CREATED_AT = 1;
    const UPDATED_AT = 0;

    const AVAILABLE = 1;
    const DISABLE = 0;

    const MAINTENANCE_FULL = 1;
    const MAINTENANCE_JUST_GET = 2;
    const MAINTENANCE_OFF = 0;

    public $switch_delete;  // dlya togo chto bi ne zapuskat v afterSave processi posle togo kak vremya deistvitelnosti maintenance budet istechen

    // public $ip_one;

    public $sizeofIpsAdmin;
    public $sizeofCountryAllowAdmin;

    public $lat_contact;
    public $lng_contact;

    const NUMBER_OF_SYMBOLS_LICENSE = 100000;


    const ADMIN_MAINTENANCE = 1;
    const SUPPORT_MAINTENANCE = 0;
    /**
     * @inheritdoc
     */
    public static function tableName(){

        return '{{%settings}}';
    }

    /**
     * @inheritdoc
     */
    public function rules(){
        return [

            ['maintenance_on_off', 'in', 'range'=>[self::MAINTENANCE_FULL, self::MAINTENANCE_JUST_GET, self::MAINTENANCE_OFF]],

            ['address_zip', 'string', 'max'=> 10],
            [['address_country', 'address_city_region'],  'string', 'max'=> 30],
            [['address_call_center'],  'string', 'max'=> 25],
            [['address_geo_title'],  'string', 'max'=> 100],

            ['address_country', 'string', 'max'=> 30],
            [['admin_mail', 'support_email', 'phone'], 'string', 'max'=>50],

            [['admin_mail', 'support_email'], 'email'],
            ['language_prefer', 'in', 'range'=> [self::EN, self::FR]],
            [['language_prefer', 'phone'] , 'required']

        ];
    }




    public function beforeSave($insert){
        if (parent::beforeSave($insert)) {

            return true;
        }
        return false;
    }



    public function afterSave($insert, $changedAttributes){
        parent::afterSave($insert, $changedAttributes);



    }

    public function attributeLabels(){

        return [
            'id' => 'ID',
            'count_number_per_user' => Yii::t('users', 'NUMBER_OF_CARDS_PER_USER'),
            'create_update_order_by' => Yii::t('users', 'ORDER_BY_C_U'),
            'sorting_desc_asc' => Yii::t('users', 'SORT_D_A'),
            'alert_days_driver_licence' => Yii::t('users', 'ALERT_AB_EXPIRE_DR_LICENCE'),
            'alert_days_insurance' => Yii::t('users', 'ALERT_AB_EXPIRE_DR_INSURANCE'),
            'delete_notification_allow_mobile' => Yii::t('users', 'ALLOW_DELETE_NOTIFICATION').'(Mobile)',
            'delete_notification_allow_web' => Yii::t('users', 'ALLOW_DELETE_NOTIFICATION').'(Web)',
            'activation_by_sms_email_mobile' => Yii::t('users', 'ACTIV_ACC_MOBILE_SMS_EMAIL'),
            'time_min_scheduled' => Yii::t('users', 'MIN_TIME_BEFORE_SCHEDULED'),
            'time_max_scheduled' => Yii::t('users', 'MAX_TIME_BEFORE_SCHEDULED'),
            'url_site' => Yii::t('users', 'Url Site'),
            'url_site_admin' => Yii::t('users', 'Url Admin Site'),
            'currency_prefer' => Yii::t('users', 'CURRENCY_PREFER'),
            'url_list_currency' => Yii::t('users', 'URL_CURRENCY'),
            'test_live_payment' => Yii::t('users', 'TEST_LIVE_PAYMENT'),
            'count_month_graph' => Yii::t('users', 'COUNT_MONTH_GRAPH'),
            'support_email' => Yii::t('users', 'Email support'),
            'count_docs_upload_max' => Yii::t('users', 'MAX_DOCS_UPLOAD'),
            'list_document_required' => Yii::t('users', 'LIST_DOCS_REQUIRED'),


            'permis_verso_doc' => Yii::t('users', 'DRIVING_LICENCE').' (verso)',
            'permis_recto_doc' => Yii::t('users', 'DRIVING_LICENCE').' (recto)',
          //  'permis_date_doc' => Yii::t('users', 'DATE_LICENCE_DRIVER'),


            'carte_vtc_verso_doc' => Yii::t('users', 'LICENCE_VTC').' (verso)',
            'carte_vtc_recto_doc' => Yii::t('users', 'LICENCE_VTC').' (recto)',
            'carte_vtc_date_doc' => Yii::t('users', 'DATE_LICENCE_VTC'),

            'kbis_doc' => Yii::t('users', 'EXTRAIT_KBIS'),
            'bulletin_3_casier_doc' => Yii::t('users', 'BUL_3_EXTRAIT_JUS'),

            'photo_profile_doc' => Yii::t('users', 'SELFI_PIC'),
            'carte_gris_doc' => Yii::t('users', 'CARTE_GRIS'),
            'carte_verte_doc' => Yii::t('users', 'CARTE_VERTE'),
            'carte_verte_date_doc' => Yii::t('users', 'VERTE_DATE'),

            'insurance_professionel_doc' => Yii::t('users', 'INSURANCE_RESPONSIBILITY_PROFESSIONAL'),  //
            'insurance_professionel_date_doc' => Yii::t('users', 'INSURANCE_RESPONSIBILITY_PROFESSIONAL').'. '.Yii::t('users', 'DATE_EXP'),

            'attestation_insurance_vehicule_doc' => Yii::t('users', 'INSURANCE_WITH_PLAQUE'),  //
            'attestation_insurance_vehicule_date_doc' => Yii::t('users', 'INSURANCE_WITH_PLAQUE').'. '.Yii::t('users', 'DATE_EXP'),

            'count_push_token_per_user' => Yii::t('users', 'MAX_DEVICES_FOR_USER'),
            'count_max_favorites' => Yii::t('users', 'MAX_NUMBER_OF_FAVORITES'),
            'count_max_promocode' => Yii::t('users', 'MAX_NUMBER_OF_PROMOCODE'),
            'language_prefer' => Yii::t('users', 'YOUR_PREFER_LANGUAGE'),
            'notification_sync_payment' => Yii::t('users', 'CHECK_SYNC_DB_AND_BT'),
            'notification_expiring_payment' => Yii::t('users', 'CARD_EXPIRING_SOON'),
            'count_min_sec_before_modal' => Yii::t('users', 'NUMBER_SEC_BEF_MODAL'),
            'notification_expired_promocode' => Yii::t('users', 'NOTIFY_ABOUT_PROMOCODE_EXPIRED'),
            'notification_expired_newsletter' => Yii::t('users', 'NOTIFY_ABOUT_NEWSLETTER_EXPIRED'),
            'braintree_stripe' => 'Braintree / Stripe',
            'alert_month_before_expiry' => Yii::t('users', 'ALERT_NM_EXPIRE_CARD'),
            'editorIps' => Yii::t('users', 'EDITOR_IPS'),
            'editorIsoCountry' => Yii::t('users', 'EDITOR_COUNTRY_ALLOW'),
            'url_android_application' => Yii::t('users', 'URL_ANDROID_APP'),
            'url_ios_application' => Yii::t('users', 'URL_IOS_APP'),
            'license' => Yii::t('users', 'LICENSE_AGREE'),
            'address_number_street' => Yii::t('users', 'ADDRESS_AND_NUMBER'),
            'address_zip' => Yii::t('users', 'ZIP'),
            'address_country' => Yii::t('users', 'COUNTRY'),
            'address_city_region' => Yii::t('users', 'CITY_REGION'),
            'address_call_center' => Yii::t('users', 'TEL_CALL_CENTER'),
            'description_offer' => Yii::t('users', 'Description'),
            'description_job_offer' => Yii::t('users', 'DESCRIPTION_JOB'),
            'description_traineeships_offer' => Yii::t('users', 'DESCRIPTION_TRAINEESHIPS'),
            'jobs_email' => Yii::t('users', 'EMAIL_FOR_JOBS'),
            'maintenance_on_off' => Yii::t('users', 'TURN_ON_MAINTENANCE'),
            'maintenance_on_off_mobile' => Yii::t('users', 'TURN_ON_MAINTENANCE'),
            'time_zone' => Yii::t('users', 'Time Zone'),
            'maintenance_message_about' => Yii::t('users', 'Maintenance message about'),
            'maintenance_message_main' => Yii::t('users', 'MAINTENANCE_MAIN_PAGE'),
            'maintenance_message_time_to' => Yii::t('users', 'MAINTENANCE_UNTIL_TO'),
            'maintenance_message_time_to_mobile' => Yii::t('users', 'MAINTENANCE_UN_T_MOBILE'),
            'campaign_id' => Yii::t('users', 'Campaign e-mail'),
            'admin_or_support_email_maintenance' => Yii::t('users', 'SEND_ON_ADMIN_OR_S_EMAIL'),
            'max_count_subscribe' => Yii::t('users', 'MAX_COUNT_SUBSCRIBE'),
            'maintenanceIps' => Yii::t('users', 'IPS_MAINTENANCE'),
            'maintenance_message_time_show' => Yii::t('users', 'SHOW_TIME_MOBILE_EN_MESS'),
            'maintenance_auto_mobile' => Yii::t('users', 'MAINTENANCE_AUTO_SWITCH_M'),
            'maintenance_auto' => Yii::t('users', 'MAINTENANCE_AUTO_SWITCH'),
            'address_geo' => Yii::t('users', 'GEO_ADDRESS'),
            'address_other_info' => Yii::t('users', 'OTHER_INFO'),
            'min_year_car' => Yii::t('users', 'YEAR_OF_CAR').' Minimum',
            'min_tarif_per_km' => Yii::t('users', 'MIN_TARIF_PER_KM'),
            'max_tarif_per_km' => Yii::t('users', 'MAX_TARIF_PER_KM'),
            'min_tarif_boarding' => Yii::t('users', 'MIN_TARIF_BOARDING'),
            'max_tarif_boarding' => Yii::t('users', 'MAX_TARIF_BOARDING'),
            'currency' => Yii::t('users', 'CURRENCY'),
            'modify_each_step_driver' => Yii::t('users', 'MODIFY_EACH_STEP_REG_DRIV'),
            'date_show_required' => Yii::t('users', 'SHOW_AND_REQUIRED_DATES'),
            'switch_show_places' => Yii::t('users', 'SHOW_NUMBER_PLACES'),
            'count_max_user_last_actions' => Yii::t('users', 'MAX_COUNT_PROFILE_ACTIONS'),
            'text_1_line_panel' => Yii::t('users', 'TEXT_LINE_LOGIN', ['count'=>1]),
            'text_2_line_panel' => Yii::t('users', 'TEXT_LINE_LOGIN', ['count'=>2]),
            'image_fond_panel_login' => Yii::t('users', 'IMAGE_FOND').'. '.Yii::t('users', 'SCREEN').' Login',
            'show_comment_passenger' => Yii::t('users', 'SHOW_PASSENGER_COMMENT'),
            'show_name_passenger' => Yii::t('users', 'SHOW_NAME_PASSENGER'),
            'enable_captcha_or_not' => Yii::t('users', 'ENABLE_CAPTCHA_OR_NOT'),
        ];
    }

    public function behaviors(){
        return [
            [
                'class' => TimestampBehavior::className()
            ],
        ];
    }


}

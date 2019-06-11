<?php

namespace console\controllers;

use backend\helpers\IpHelper;
use backend\helpers\StringHelper;
use backend\models\Process;
use common\models\Query;
use common\repositories\read\ShopsReadRepositories;
use console\extended\checkDirectory;
use console\helpers\Utils;
use console\interfaces\ShopInterface;
use console\repositories\mailRepositorie\sendMail;
use Yii;
use yii\console\Controller;

/**
 * Class AdidasController
 * @package console\controllers
 * @property  \yii\db\Connection $connection
 */
class AdidasController extends Controller {
    const NAME_ADIDAS_MEN = 'adidas_men';
    const NAME_ADIDAS_WOMEN = 'adidas_women';
    const NAME_ADIDAS_GARCONS = 'adidas_garcons';
    const NAME_ADIDAS_FILLES = 'adidas_filles';

    private $checkDirectory;
    private $last_product_id;
    private $got_last_product_id;
    private $mailer;
    private $path_sql_path;

    //___Meta info for file
    private $start_product_id;
    private $end_product_id;

    private $tableau_sex_ass;
    private $_name_directory = ''; //___ 158790909  //  1 raz prisvaivaem pri pervom sozdanii irarxii papok
    private $shopReadRepositories;


    /**
     * AdidasController constructor
     * @param string $id
     * @param $module
     * @param sendMail $mailer
     * @param checkDirectory $checkDirectory
     * @param ShopsReadRepositories $shopReadRepositories
     * @param array $config
     */
    public function __construct(string $id, $module,
                                sendMail $mailer,
                                checkDirectory $checkDirectory,
                                ShopsReadRepositories $shopReadRepositories,
                                $config = [])
    {
        parent::__construct($id, $module, $config);
        $this->checkDirectory = $checkDirectory;
        $this->shopReadRepositories = $shopReadRepositories;
        $this->mailer = $mailer;

        $this->tableau_sex_ass = [
            self::NAME_ADIDAS_MEN => 0,
            self::NAME_ADIDAS_WOMEN => 1,
            self::NAME_ADIDAS_GARCONS => 2,
            self::NAME_ADIDAS_FILLES => 3
        ];
    }

//    /**
//     * @param \yii\base\Action $action
//     * @return bool
//     * @throws \Exception
//     */
//    public function beforeAction($action) {
//        if (!Utils::checkParseOrNot()) // __ Proveryaem admin dal li razreshenie
//            return false;
//        return parent::beforeAction($action);
//    }

    public function actionInsert() {
        $this->checkDirectory->init('adidas');
    }

    /**
     * @throws \Exception
     */
    public function actionMakeListMen() {
        $this->scanForList(
            'https://www.adidas.fr/chaussures-hommes?start=',
            self::NAME_ADIDAS_MEN);
    }

    /**
     * @throws \Exception
     */
    public function actionMakeListWomen() {
        $this->scanForList(
            'https://www.adidas.fr/chaussures-femmes?start=',
            self::NAME_ADIDAS_WOMEN);
    }

    /**
     * @throws \Exception
     */
    public function actionMakeListGarcons() {
        $this->scanForList(
            'https://www.adidas.fr/garcons-adolescents_8_16_ans?start=',
            self::NAME_ADIDAS_GARCONS);
    }

    /**
     * @throws \Exception
     */
    public function actionMakeListFilles() {
        $this->scanForList(
            'https://www.adidas.fr/filles-adolescents_8_16_ans?start=',
            self::NAME_ADIDAS_FILLES);
    }

    /**
     * @param $url
     * @param $category
     */
    private function scanForList($url, $category) {
        Utils::checkDirectoryForListProductCreated();

        try {
            Utils::checkScanList($category);
        }
        catch (\Exception $e) {
            Utils::log($e->getMessage(), 'adidas');
            return;
        }

        $row_exists = Query::find()->where(['status' => ShopInterface::PROCESSING])->one();  //__ Stavit block block
        if ($row_exists['id']) {
            Utils::log('>>> Other query in processing. ' . $row_exists['explication'], 'adidas');
            return;
        }

        $query = new Query();
        $query->name_shop = $category;
        $query->status = ShopInterface::PROCESSING;
        $query->explication = 'Delaem parsing dlya kategorii ' . $category;
        $query->save();

        $transaction = Yii::$app->db->beginTransaction();
        $fo = null;
        try {
            $pattern = '/(?<=window\.DATA_STORE\ =)[\w\W]*?"myaccount":\{"alerts":\{}}}/';
            $fo = fopen(ShopInterface::LIST_SHOP_PRODUCTS . DIRECTORY_SEPARATOR . $category, 'w');
            $shop_id = $this->shopReadRepositories->getIdByCategory($category);

            //__ $i - eto navernoe kolichestvo stranich. Ne mojem znat zaranee sklko ix i poetomu pishem "na ugad" :)
            for ($i = 0; $i < 20; $i++) {
                //____  Nakaplivaem url stranic START
                echo $url . $i * 48 . PHP_EOL;
                $curl_scraped_page = $this->curlRequest($url . $i * 48);  //__ 48 - kolichestvo krossovok na straniche
                Process::updateActivity($shop_id, Process::LIST_MAKE, $curl_scraped_page);
                preg_match($pattern, $curl_scraped_page, $matches);

                if (count($matches) == 1) { //__ Esli tolko 1 sovpadenie, to est chto nam i nujno -> vipolnyaem kod
                    $list_items = json_decode($matches[0])->plp->itemList->items;

                    for ($j = 0; $j < count($list_items); $j++) {
                        $extrair_last_part = $this->getReference($list_items[$j]->link);
                        $all_colors = $list_items[$j]->colorVariations;  //__ Vse cveta v massive
                        $base_path_url = str_replace($extrair_last_part, '', $list_items[$j]->link);

                        for ($p = 0; $p < sizeof($all_colors); $p++)  //__ Delaem podstanovku s chvetami i obrazuem novie URL
                            fwrite($fo, 'https://www.adidas.fr' . $base_path_url . $all_colors[$p] . '.html' . PHP_EOL);
                    }
                }
                else
                    echo "Net sovpadeniya " . PHP_EOL;

                unset($matches);
            }

            $transaction->commit();
            Query::deleteAll(['status' => Query::PROCESSING]);
        }
        catch (\Exception $e) {
            Utils::log('Get url for parsing. ' . $category . ' ' . $this->_name_directory . ' ' . $e->getMessage(), 'adidas');
            Query::deleteAll(['status' => Query::PROCESSING]);
            $transaction->rollBack();
//            $this->mailer->sendEmailGeneralError('Get url for parsing. ' . $category . ' ' . $this->_name_directory, $e->getMessage());
        }
        finally {
            if ($fo)
                fclose($fo);
        }
    }


    /**
     * @param $url_string
     * @return string $return_reference
     */
    private function getReference($url_string) {
        $extrair_last_part = explode("/", $url_string);
        $return_reference = $extrair_last_part[count($extrair_last_part) - 1];
        return $return_reference;
    }

    /**
     * @throws \Exception
     */
    public function actionParsePageMen() {
        $this->parsePage(self::NAME_ADIDAS_MEN);
    }

    /**
     * @throws \Exception
     */
    public function actionParsePageWomen() {
        $this->parsePage(self::NAME_ADIDAS_WOMEN);
    }

    /**
     * @param $category
     * @throws \yii\db\Exception
     */
    private function parsePage($category) {
        $path_to_list_file = ShopInterface::LIST_SHOP_PRODUCTS . DIRECTORY_SEPARATOR . $category;
        //__ Proveryaem suschestvovaniya etogo faila
        if (!file_exists($path_to_list_file) || !is_file($path_to_list_file)) {
            Utils::log('Net faila-spiska dlya ' . $category, 'adidas');
            return;
        }

        $data = file_get_contents($path_to_list_file); //read the file
        $lines = explode(PHP_EOL, $data); //create array separate by new line
        array_pop($lines);

        $row_exists = Query::find()->where(['status' => ShopInterface::PROCESSING])->one();  //__ Stavit block block
        if ($row_exists['id']) {
            Utils::log('>>> Other query in processing. ' . $row_exists['explication'], 'adifas');
            return;
        }

        $query = new Query();
        $query->name_shop = $category;
        $query->status = ShopInterface::PROCESSING;
        $query->explication = 'Kachaem iz saita infu (kartinki + sql fail). Shop = ' . $category;
        $query->save();  // @todo aktivirovat

        $db = Yii::$app->db;
        try {
            $list_shop_directory = Utils::scanDir(ShopInterface::DIR_STATIC);
            if (!$list_shop_directory)
                $this->last_product_id = $db->createCommand('SELECT MAX(id) AS id FROM product_copy')->queryScalar() + 1;
            else {
                $array_shop_plus_id_number_start = $this->checkDirectory->check($list_shop_directory, 'end');
                rsort($array_shop_plus_id_number_start);
                $id_for_read_sql = array_values($array_shop_plus_id_number_start);

                if ($id_start_insert = array_shift($id_for_read_sql))
                    $this->last_product_id = $id_start_insert;
                else
                    throw new \Exception('не найдены sql файлы');
            }

            $this->start_product_id = $this->last_product_id;
            if (!$this->_name_directory = Utils::prepareDirectory($category)) //__ Podgatavlivaem katalog dlya razmescheniya failov
                throw new \Exception('Не удалось создать timestamp папку');

            $this->path_sql_path = $this->_name_directory . DIRECTORY_SEPARATOR . $category . '_sql.sql';
            $this->got_last_product_id = false; // ___ flag dlya opredeleniya togo esli mi yje vstavili ili bet SET @product_id= v faile sql
            $timeStampImages = time(); //__ Dlya kartinok bez smisla chto k chemu oto otnostsya
            $shop_id = $this->shopReadRepositories->getIdByCategory($category);

            for ($i = 0; $i < count($lines) + 1; $i++) {
                $curl_scraped_page_av = $this->curlAvailable($lines[$i]);
                // echo $curl_scraped_page_av;
                $peremennaya = json_decode($curl_scraped_page_av);

                if (!isset($curl_scraped_page_av) or empty($curl_scraped_page_av) or !isset($peremennaya->id))
                    continue;

                $article = $peremennaya->id;
                $curl_scraped_page = $this->curlRequest($lines[$i]);
                Process::updateActivity($shop_id, Process::PARSING_PRODUCT, $curl_scraped_page);
                $pattern = '/(?<=window\.DATA_STORE\ =)[\w\W]*?"myaccount":\{"alerts":\{}}}/';
                preg_match($pattern, $curl_scraped_page, $matches);

                if (!isset($matches[0]) or empty($matches[0]))
                    continue;

                $peremennaya_html = json_decode($matches[0]);
                $price_reg = '/(?<=gl-price__value">).*?(?=<\/span>)/';
                preg_match($price_reg, $curl_scraped_page, $matches_price);

                if (isset($peremennaya_html->product->pricing_information->currentPrice) and !empty($peremennaya_html->product->pricing_information->currentPrice))
                    $price = trim($peremennaya_html->product->pricing_information->currentPrice);
                else
                    $price = '';

                $sql = '';
                $row_exist = $db->createCommand('SELECT article, id, product_id FROM shoes_copy WHERE article = "' . $article . '";')->queryOne();
                if ($row_exist['id']) { // __ Esli u nas est yje takoi produkt v BD to delaem UPDATE
                    $sql .= '/* ' . $row_exist['product_id'] . '  ' . $lines[$i] . ' */' . PHP_EOL;
                    $sql .= 'DELETE FROM size_copy WHERE product_id = ' . $row_exist["product_id"] . ';' . PHP_EOL;
                    $sql_size = $this->getSqlSizeString($peremennaya->variation_list, $row_exist["product_id"]);

                    //__ Esli razmeru ukazani to ix vstavlyaem v tablichu
                    if (isset($sql_size))
                        $sql .= 'INSERT INTO size_copy (product_id, size, created_at, updated_at) VALUES ' . $sql_size . ';' . PHP_EOL;

                    ///__ Esli est chena na straniche to delaem obnovlenie cheni
                    if (isset($matches_price[0]) AND !empty($matches_price[0])) {
                        $price = $this->getCurrencyInt($matches_price[0]);
                        $sql .= 'UPDATE shoes_copy SET price = "' . $price . '", updated_at = ' . $timeStampImages . ' WHERE product_id = ' . $row_exist["product_id"] . ';' . PHP_EOL;
                    }
                }
                else {  //__ Esli u nas est net takoi produkt v BD to delaem INSERT
                    $this->got_last_product_id = true;
                    $sql .= '/* ' . $this->last_product_id . '  ' . $lines[$i] . ' */' . PHP_EOL;
                    $sql .= 'INSERT INTO product_copy (id, shop_id, type_id, created_at, updated_at) VALUES (' . $this->last_product_id . ', ' . $shop_id . ', 1, ' . $timeStampImages . ', ' . $timeStampImages . ');' . PHP_EOL;

                    if (isset($peremennaya_html->product->product_description->text) and !empty($peremennaya_html->product->product_description->text))
                        $description = StringHelper::slashEscape(trim($peremennaya_html->product->product_description->text));
                    else
                        $description = '';

                    $description = str_replace(["\r\n", "\r", "\n"], '', $description);

                    if (isset($peremennaya_html->product->product_description->usps[0]) and !empty($peremennaya_html->product->product_description->usps[0]))
                        $fermeture = StringHelper::slashEscape(trim($peremennaya_html->product->product_description->usps[0]));
                    else
                        $fermeture = '';

                    if (isset($peremennaya_html->product->product_description->title) and !empty($peremennaya_html->product->product_description->title))
                        $name = StringHelper::slashEscape(trim($peremennaya_html->product->product_description->title));
                    else
                        $name = '';

                    if (isset($peremennaya_html->product->attribute_list->color) and !empty($peremennaya_html->product->attribute_list->color))
                        $color = StringHelper::slashEscape(trim($peremennaya_html->product->attribute_list->color));
                    else
                        $color = '';

                    if (isset($peremennaya_html->product->attribute_list->brand) and !empty($peremennaya_html->product->attribute_list->brand))
                        $sub_name = 'Adidas ' . StringHelper::slashEscape(trim($peremennaya_html->product->attribute_list->brand));
                    else
                        $sub_name = 'Adidas';

                    if (isset($peremennaya_html->product->attribute_list->brand) and !empty($peremennaya_html->product->attribute_list->brand))
                        $brand = StringHelper::slashEscape(trim($peremennaya_html->product->attribute_list->brand));
                    else
                        $brand = 'Adidas';

                    if (isset($matches_price[0]) and !empty($matches_price[0]))
                        $currency = $this->getCurrencyInt($matches_price[0]);

                    $sex = $this->tableau_sex_ass[$category];

                    $sql .= 'INSERT INTO shoes_copy (product_id, name, sub_name, fermeture, currency, url, sex, brand,  description, color, price, article,  created_at, updated_at) VALUES (' . $this->last_product_id . ', "' . $name . '", "' . $sub_name . '", "' . $fermeture . '", ' . $currency . ', "' . $lines[$i] . '", ' . $sex . ', "' . $brand . '", "' . $description . '", "' . $color . '", "' . $price . '", "' . $article . '", ' . time() . ' , ' . time() . ' );' . PHP_EOL;

                    //___  Razmeri START
                    $sql_size = $this->getSqlSizeString($peremennaya->variation_list, $this->last_product_id);
                    $sql .= 'INSERT INTO size_copy (product_id, size, created_at, updated_at) VALUES ' . $sql_size . ';' . PHP_EOL;
                    //___  Razmeri STOP

                    //___ Kartinki START  ________
                    $sql_images = '';

                    $fp_add_exception = fopen('adidas_steret1.html', 'w');
                    fwrite($fp_add_exception, json_encode($peremennaya_html));
                    fclose($fp_add_exception);

                    if (isset($peremennaya_html->product->view_list)) {
                        $pict_my = $peremennaya_html->product->view_list;
                        $pic_outs = [];
                        for ($e = 0; $e < count($pict_my); $e++) {
                            if ($pict_my[$e]->type == 'standard' and $pict_my[$e]->image_url) {
                                $full_size_pic = str_replace('w_600', 'w_2000', $pict_my[$e]->image_url);
                                array_push($pic_outs, $full_size_pic);
                            }
                        }

                        if (count($pic_outs)) { //__ Esli voobsche est kartinki to skachivaem ix  + dobavlyaem v bazu
                            for ($pi = 0; $pi < count($pic_outs); $pi++) {
                                $name_file = StringHelper::randomString(20, null, true);
                                if ($this->saveImages($pic_outs[$pi], $this->_name_directory, $name_file))  //__ Esli kartinka est
                                    $sql_images .= '(' . $this->last_product_id . ', "' . $name_file . '.jpg", ' . $timeStampImages . ', ' . $timeStampImages . ')' . ',';
                            }

                            if (strlen($sql_images)) {
                                $sql_images = substr($sql_images, 0, -1);
                                $sql .= 'INSERT INTO images_copy (product_id, patch, created_at, updated_at)  VALUES ' . $sql_images . ';' . PHP_EOL;
                            }
                        }
                    }
                    //___ Kartinki END ________

                    $this->last_product_id++;  //__ Delaem prirascheni id dlya vstavki v BD
                }

                $fp_add = fopen($this->path_sql_path, 'a');
                fwrite($fp_add, $sql);
                fclose($fp_add);
            }

            $this->last_product_id++;
            $this->checkDirectory->reconstructionSqlFile($this->path_sql_path, $this->start_product_id, $this->last_product_id);
            Utils::log('>>> Adidas zakoncheno <<<', 'adidas');
            $db->createCommand('DELETE FROM ' . Query::tableName() . ' WHERE name_shop = "' . $category . '"')->execute();
        }
        catch (\Exception $e) {
            Utils::log('Error of Generation sql file. ' . $category . '. ' . $this->_name_directory . '. ' . $e->getLine() . ' - ' . $e->getMessage(), 'adidas');
//            $this->mailer->sendEmailGeneralError('Error of Generation sql file. ' . $category . '. ' . $this->_name_directory, $e->getLine() . ', ' . $e->getMessage() . PHP_EOL . $e->getTraceAsString());
            $db->createCommand('DELETE FROM ' . Query::tableName() . ' WHERE name_shop = "' . $category . '" ')->execute();
        }
    }

    /**
     * @param $list
     * @param $product_id
     * @return bool|string
     */
    private function getSqlSizeString($list, $product_id) {
        $sql_size = '';
        for ($fu = 0; $fu < count($list); $fu++) {
            if ($list[$fu]->availability_status == 'IN_STOCK') {
                $sql_size .= '(' . $product_id . ', "' . $list[$fu]->size . '", ' . time() . ', ' . time() . ')' . ',';
            }
        }

        if (substr($sql_size, -1) == ',')
            $sql_size = substr($sql_size, 0, -1);

        return $sql_size;
    }

    /**
     * @param $string_price
     * @return bool|int
     */
    private function getCurrencyInt($string_price) {
        $output = explode(" ", $string_price);

        switch ($output[1]) {
            case  '€' :
                $currency = 1;
                break;
            case '$':
                $currency = 2;
                break;
            case '£':
                $currency = 3;
                break;
            default:
                $currency = false;
        }

        return $currency;
    }

    private function saveImages($url, $path, $name_file) {
        $path .= DIRECTORY_SEPARATOR . ShopInterface::IMAGES_DIRECTORY;
        $first_directory = substr($name_file, 0, 2);
        $second_directory = substr($name_file, 2, 2);

        if (!file_exists($path . DIRECTORY_SEPARATOR . $first_directory))
            mkdir($path . DIRECTORY_SEPARATOR . $first_directory);

        if (!file_exists($path . DIRECTORY_SEPARATOR . $first_directory . DIRECTORY_SEPARATOR . $second_directory))
            mkdir($path . DIRECTORY_SEPARATOR . $first_directory . DIRECTORY_SEPARATOR . $second_directory);

        $path_to_save = $path . DIRECTORY_SEPARATOR . $first_directory . DIRECTORY_SEPARATOR . $second_directory . DIRECTORY_SEPARATOR . $name_file . '.jpg';

        // $exec = 'curl -o ' . $path_to_save . ' -XGET -H \'cache-control: max-age=0\' -H \'accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8\' -H \'Accept-Language: en\' -H \'accept-encoding: deflate, br\' -H \'User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_2) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/72.0.3626.119 Safari/537.36\' -H \'Content-type: text/html; charset=UTF-8\' -H \'cookie: geo_ip=81.57.211.146; geo_country=FR; onesite_country=FR; akacd_plp_prod_adidas_grayling=3728753007~rv=96~id=dd8689155030c516831d7589576b7a70; bm_sz=233F1563FD253B83E90047C24676654C~YAAQRJM1VMqscjBpAQAAZ6qyMALUUvq+aH+3DrI5zxO4lzdtf7b86t4cwIilmWiR8BRqVyBJvoij37+OyQw3Mb+qzMlTbdy2YiyPj8l054HQn2E9NhNWVpApH01qDJy4+CYn6PUDOxo9ckZnNtLHMF6aHJ8TGFk41Q8HBV25nypa+wl/rtO7aH5aLIPLtw==; ab_monetate=a; MOBILE_ZOOM_ALERT_COOKIE=true; default_searchTerms_CustomizeSearch=%5B%5D; geoRedirectionAlreadySuggested=false; wishlist=%5B%5D; persistentBasketCount=0; UserSignUpAndSave=1; ab_optimizely=a; mt.v=5.1945272072.1551300211006; optimizelyEndUserId=oeu1551300211891r0.8896790619024113; cvPT=PLPT; akacd_phasedRC_Row=3728753010~rv=60~id=f1c251d43418f4df5130020b0de7c960; __adi_rt_DkpyPh8=CRTOH2H; inf_media_split=test; ab_decibel=a; RES_TRACKINGID=93583966541200315; ResonanceSegment=1; RES_SESSIONID=12637927541200315; _gcl_au=1.1.1909989029.1551300215; AMCVS_7ADA401053CCF9130A490D4C%40AdobeOrg=1; _ga=GA1.2.621567098.1551300216; _gid=GA1.2.93273394.1551300216; s_cc=true; AMCV_7ADA401053CCF9130A490D4C%40AdobeOrg=-227196251%7CMCIDTS%7C17955%7CMCMID%7C64697849960691199666275063761991103277%7CMCAAMLH-1551905015%7C6%7CMCAAMB-1551905015%7CRKhpRz8krg2tLO6pguXWp5olkAcUniQYPHaMWWgdJ3xzPWQmdj0y%7CMCOPTOUT-1551307416s%7CNONE%7CMCAID%7CNONE; QSI_HistorySession=https%3A%2F%2Fwww.adidas.fr%2Fchaussures-hommes%3Fstart%3D0~1551300216920; _scid=450709c0-4b51-4d94-a6b7-81296057cc38; notice_preferences=2:; notice_gdpr_prefs=0,1,2:; utag_main=v_id:016930b2be240007d5b76b63a05003079002f0710093c$_sn:1$_se:2$_ss:0$_st:1551302021437$ses_id:1551300214310%3Bexp-session$_pn:1%3Bexp-session$_prevpage:PLP%7CG_MEN%7CPR_SHOES%3Bexp-1551303814534; cvOptiProfile={"MEN":1,"LVG":"M"}; cmapi_gtm_bl=; cmapi_cookie_privacy=permit 1,2,3; _abck=76105D174578A5159651F34034206C3654359344142C000071F6765C52C4A775~0~F2FkoFKJWyV+jm7lCunCRmezDp7L9NYsWNV0A6IdiB8=~-1~-1; s_tps=692; s_pvs=45; s_pers=%20s_vnum%3D1551394800996%2526vn%253D1%7C1551394800996%3B%20s_visit%3D1%7C1551302021455%3B%20pn%3D1%7C1553892221470%3B%20s_invisit%3Dtrue%7C1551302331862%3B; RT="z=1&dm=adidas.fr&si=d8e519c6-91c3-4e88-b7b4-43d78ee7e898&ss=jsnndqqd&sl=1&tt=210&bcn=%2F%2F0211c84d.akstat.io%2F&ul=wgh7&hd=wgo1"\' "' . $url . '"';
        $exec = 'curl -XGET -H \'cache-control: max-age=0\' -H \'accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8\' -H \'Accept-Language: en\' -H \'accept-encoding: deflate, br\' -H \'User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_2) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/72.0.3626.119 Safari/537.36\' -H \'Content-type: text/html; charset=UTF-8\' -H \'cookie: geo_ip=81.57.211.146; geo_country=FR; onesite_country=FR; akacd_plp_prod_adidas_grayling=3728753007~rv=96~id=dd8689155030c516831d7589576b7a70; bm_sz=233F1563FD253B83E90047C24676654C~YAAQRJM1VMqscjBpAQAAZ6qyMALUUvq+aH+3DrI5zxO4lzdtf7b86t4cwIilmWiR8BRqVyBJvoij37+OyQw3Mb+qzMlTbdy2YiyPj8l054HQn2E9NhNWVpApH01qDJy4+CYn6PUDOxo9ckZnNtLHMF6aHJ8TGFk41Q8HBV25nypa+wl/rtO7aH5aLIPLtw==; ab_monetate=a; MOBILE_ZOOM_ALERT_COOKIE=true; default_searchTerms_CustomizeSearch=%5B%5D; geoRedirectionAlreadySuggested=false; wishlist=%5B%5D; persistentBasketCount=0; UserSignUpAndSave=1; ab_optimizely=a; mt.v=5.1945272072.1551300211006; optimizelyEndUserId=oeu1551300211891r0.8896790619024113; cvPT=PLPT; akacd_phasedRC_Row=3728753010~rv=60~id=f1c251d43418f4df5130020b0de7c960; __adi_rt_DkpyPh8=CRTOH2H; inf_media_split=test; ab_decibel=a; RES_TRACKINGID=93583966541200315; ResonanceSegment=1; RES_SESSIONID=12637927541200315; _gcl_au=1.1.1909989029.1551300215; AMCVS_7ADA401053CCF9130A490D4C%40AdobeOrg=1; _ga=GA1.2.621567098.1551300216; _gid=GA1.2.93273394.1551300216; s_cc=true; AMCV_7ADA401053CCF9130A490D4C%40AdobeOrg=-227196251%7CMCIDTS%7C17955%7CMCMID%7C64697849960691199666275063761991103277%7CMCAAMLH-1551905015%7C6%7CMCAAMB-1551905015%7CRKhpRz8krg2tLO6pguXWp5olkAcUniQYPHaMWWgdJ3xzPWQmdj0y%7CMCOPTOUT-1551307416s%7CNONE%7CMCAID%7CNONE; QSI_HistorySession=https%3A%2F%2Fwww.adidas.fr%2Fchaussures-hommes%3Fstart%3D0~1551300216920; _scid=450709c0-4b51-4d94-a6b7-81296057cc38; notice_preferences=2:; notice_gdpr_prefs=0,1,2:; utag_main=v_id:016930b2be240007d5b76b63a05003079002f0710093c$_sn:1$_se:2$_ss:0$_st:1551302021437$ses_id:1551300214310%3Bexp-session$_pn:1%3Bexp-session$_prevpage:PLP%7CG_MEN%7CPR_SHOES%3Bexp-1551303814534; cvOptiProfile={"MEN":1,"LVG":"M"}; cmapi_gtm_bl=; cmapi_cookie_privacy=permit 1,2,3; _abck=76105D174578A5159651F34034206C3654359344142C000071F6765C52C4A775~0~F2FkoFKJWyV+jm7lCunCRmezDp7L9NYsWNV0A6IdiB8=~-1~-1; s_tps=692; s_pvs=45; s_pers=%20s_vnum%3D1551394800996%2526vn%253D1%7C1551394800996%3B%20s_visit%3D1%7C1551302021455%3B%20pn%3D1%7C1553892221470%3B%20s_invisit%3Dtrue%7C1551302331862%3B; RT="z=1&dm=adidas.fr&si=d8e519c6-91c3-4e88-b7b4-43d78ee7e898&ss=jsnndqqd&sl=1&tt=210&bcn=%2F%2F0211c84d.akstat.io%2F&ul=wgh7&hd=wgo1"\' "' . $url . '"';

        $curl_scraped_page = '';
        if ($proc = popen("($exec)2>&1", "r")) {
            while (!feof($proc))
                $curl_scraped_page .= fgets($proc, 2000);
            pclose($proc);
        }

        if (strlen($curl_scraped_page) < 1000)
            return false;

        $fp_image = fopen($path_to_save, 'x');
        fwrite($fp_image, $curl_scraped_page);
        fclose($fp_image);

        return true;
    }

    /**
     * @param $url_referer // refer toi stranichi na kotoroi ya delau etot zapros.
     * @return string
     */
    private function curlAvailable($url_referer) {
        $referer = substr($this->getReference($url_referer), 0, -5);;
        $url = 'https://www.adidas.fr/api/products/' . $referer . '/availability';

        // $randIP = "" . mt_rand(0, 255) . "." . mt_rand(0, 255) . "." . mt_rand(0, 255) . "." . mt_rand(0, 255);
        $randIP = IpHelper::generateIp();
        $exec = 'curl -s -XGET  -H \'cookie: geo_ip=' . $randIP . '; geo_country=FR; onesite_country=FR; akacd_plp_prod_adidas_grayling=3728753007~rv=96~id=dd8689155030c516831d7589576b7a70; ab_monetate=a; MOBILE_ZOOM_ALERT_COOKIE=true; default_searchTerms_CustomizeSearch=%5B%5D; geoRedirectionAlreadySuggested=false; wishlist=%5B%5D; persistentBasketCount=0; ab_optimizely=a; mt.v=5.1945272072.1551300211006; optimizelyEndUserId=oeu1551300211891r0.8896790619024113; akacd_phasedRC_Row=3728753010~rv=60~id=f1c251d43418f4df5130020b0de7c960; __adi_rt_DkpyPh8=CRTOH2H; inf_media_split=test; ab_decibel=a; RES_TRACKINGID=93583966541200315; ResonanceSegment=1; _gcl_au=1.1.1909989029.1551300215; AMCVS_7ADA401053CCF9130A490D4C%40AdobeOrg=1; _ga=GA1.2.621567098.1551300216; s_cc=true; _scid=450709c0-4b51-4d94-a6b7-81296057cc38; notice_preferences=2:; notice_gdpr_prefs=0,1,2:; cmapi_gtm_bl=; cmapi_cookie_privacy=permit 1,2,3; _abck=76105D174578A5159651F34034206C3654359344142C000071F6765C52C4A775~0~F2FkoFKJWyV+jm7lCunCRmezDp7L9NYsWNV0A6IdiB8=~-1~-1; akacd_phasedRC_homepage_FI=3728768997~rv=71~id=1dc9c7a95d9585ed5646f233a6333ac4; akacd_generic_prod_grayling_adidas=3728768998~rv=82~id=58b17fa5e18d965c7cb748cdae4c3825; bm_sz=00B0860A0482CC6D5E86BF1DA03FF9FC~YAAQRHPdWMQPzzRpAQAAu2+rNQLyx7UwpYHxMFZh/HpsPMFIFsASuV9vCP6dkHhPeLS7soDvQ30hkCy+LqUgsOQ776iAoELk/jajcUbLxsSd8u7ka1Vsbq1nZR7qgc3WzWLB2DupsvoyaNmwf3vt5PUFtOcAfURBqoG4iK6jelyfZCIj4gor6q9N4C89r30=; ak_bmsc=70BE3259C1F8E0AF6F42247959BF3B7858DD7344894B0000A759785CD243EB66~pllf0YEaHnbXdB3fd7xZ1yEqG96r5uF+xZDN9ibLPKWf88nQmpykPU/W2NG5wxNXVKIlDf2K4/nJN+rmnpOVMQmVtwsqY46VK6SzVo7MIIFo+aosojEOFOCZyn02n/hjD9Z02BZtO3nh+i2x9NZ489/qdUagT7F/1DOfh8JWbI+MGl5XOxN+EDfsrYucsXGK/gQjS6mPAqU9kNJ7fbgvYCdEzLr4OZ9xAyqE8Q5CW9BMRX09NKk8h/EhnfPim9zaiU+k9NYzwyv8D0QEXwH5LUytyLXYkE1ZYmvyHXgpqeFVQ=; akacd_phasedRC_gender=3728846292~rv=91~id=facdb944f00e35af2537bfc79411512b; RES_SESSIONID=57623977011639315; _gid=GA1.2.1120738687.1551393611; AMCV_7ADA401053CCF9130A490D4C%40AdobeOrg=-227196251%7CMCIDTS%7C17955%7CMCMID%7C64697849960691199666275063761991103277%7CMCAAMLH-1551905015%7C6%7CMCAAMB-1551998411%7CRKhpRz8krg2tLO6pguXWp5olkAcUniQYPHaMWWgdJ3xzPWQmdj0y%7CMCOPTOUT-1551400811s%7CNONE%7CMCAID%7CNONE; AKA_A2=A; QSI_HistorySession=https%3A%2F%2Fwww.adidas.fr%2Fchaussure-marquee-boost-low%2FD96931~1551395381504; BVBRANDID=f7196492-3073-4e5a-bdba-b7ad8d4b492f; BVBRANDSID=441363fc-6e08-41d9-832f-5f37306c4d78; s_sess=%5B%5BB%5D%5D; UserSignUpAndSave=9; cvPT=PDPF; cvOptiProfile={"MEN":5,"LVG":"M","ORIGINALS":1}; utag_main=v_id:016930b2be240007d5b76b63a05003079002f0710093c$_sn:2$_se:37$_ss:0$_st:1551398225189$ses_id:1551393610378%3Bexp-session$_pn:5%3Bexp-session$_prevpage:PRODUCT%7CCHAUSSURE%20CONTINENTAL%2080%20(G27706)%3Bexp-1551399829017; s_pers=%20pn%3D1%7C1553892221470%3B%20s_vnum%3D1554069600858%2526vn%253D1%7C1554069600858%3B%20s_visit%3D1%7C1551398225203%3B%20s_invisit%3Dtrue%7C1551398225207%3B; s_tps=1044; s_pvs=7839; RT="z=1&dm=adidas.fr&si=d8e519c6-91c3-4e88-b7b4-43d78ee7e898&ss=jsp7p3dc&sl=7&tt=5nj&bcn=%2F%2F36c3fef2.akstat.io%2F&ul=1ol9n&hd=1olen"\' -H \'alexatoolbar-alx_ns_ph: AlexaToolbar/alx-4.0.3\' -H \'accept-language: fr-FR,fr;q=0.9,en-US;q=0.8,en;q=0.7,ru;q=0.6,zh-TW;q=0.5,zh;q=0.4\' -H \'x-instana-t: b912c9358e1342ba\' -H \'x-instana-l: 1\' -H \'content-type: application/json\' -H \'accept: */*\' -H \'user-agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_2) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/72.0.3626.119 Safari/537.36\' -H \'referer: ' . $url_referer . '\' -H \'authority: www.adidas.fr\' -H \'accept-encoding:  deflate, br\' -H \'x-instana-s: 8f15af16a42aab3c\' --compressed "' . $url . '"';  // t.io%2F&ul=wgh7&hd=wgo1"\' "' . $url . '"';

        // curl 'https://www.adidas.fr/api/products/G27706/availability' -H 'cookie: geo_ip=81.57.211.146; geo_country=FR; onesite_country=FR; akacd_plp_prod_adidas_grayling=3728753007~rv=96~id=dd8689155030c516831d7589576b7a70; ab_monetate=a; MOBILE_ZOOM_ALERT_COOKIE=true; default_searchTerms_CustomizeSearch=%5B%5D; geoRedirectionAlreadySuggested=false; wishlist=%5B%5D; persistentBasketCount=0; ab_optimizely=a; mt.v=5.1945272072.1551300211006; optimizelyEndUserId=oeu1551300211891r0.8896790619024113; akacd_phasedRC_Row=3728753010~rv=60~id=f1c251d43418f4df5130020b0de7c960; __adi_rt_DkpyPh8=CRTOH2H; inf_media_split=test; ab_decibel=a; RES_TRACKINGID=93583966541200315; ResonanceSegment=1; _gcl_au=1.1.1909989029.1551300215; AMCVS_7ADA401053CCF9130A490D4C%40AdobeOrg=1; _ga=GA1.2.621567098.1551300216; s_cc=true; _scid=450709c0-4b51-4d94-a6b7-81296057cc38; notice_preferences=2:; notice_gdpr_prefs=0,1,2:; cmapi_gtm_bl=; cmapi_cookie_privacy=permit 1,2,3; _abck=76105D174578A5159651F34034206C3654359344142C000071F6765C52C4A775~0~F2FkoFKJWyV+jm7lCunCRmezDp7L9NYsWNV0A6IdiB8=~-1~-1; akacd_phasedRC_homepage_FI=3728768997~rv=71~id=1dc9c7a95d9585ed5646f233a6333ac4; akacd_generic_prod_grayling_adidas=3728768998~rv=82~id=58b17fa5e18d965c7cb748cdae4c3825; bm_sz=00B0860A0482CC6D5E86BF1DA03FF9FC~YAAQRHPdWMQPzzRpAQAAu2+rNQLyx7UwpYHxMFZh/HpsPMFIFsASuV9vCP6dkHhPeLS7soDvQ30hkCy+LqUgsOQ776iAoELk/jajcUbLxsSd8u7ka1Vsbq1nZR7qgc3WzWLB2DupsvoyaNmwf3vt5PUFtOcAfURBqoG4iK6jelyfZCIj4gor6q9N4C89r30=; ak_bmsc=70BE3259C1F8E0AF6F42247959BF3B7858DD7344894B0000A759785CD243EB66~pllf0YEaHnbXdB3fd7xZ1yEqG96r5uF+xZDN9ibLPKWf88nQmpykPU/W2NG5wxNXVKIlDf2K4/nJN+rmnpOVMQmVtwsqY46VK6SzVo7MIIFo+aosojEOFOCZyn02n/hjD9Z02BZtO3nh+i2x9NZ489/qdUagT7F/1DOfh8JWbI+MGl5XOxN+EDfsrYucsXGK/gQjS6mPAqU9kNJ7fbgvYCdEzLr4OZ9xAyqE8Q5CW9BMRX09NKk8h/EhnfPim9zaiU+k9NYzwyv8D0QEXwH5LUytyLXYkE1ZYmvyHXgpqeFVQ=; akacd_phasedRC_gender=3728846292~rv=91~id=facdb944f00e35af2537bfc79411512b; RES_SESSIONID=57623977011639315; _gid=GA1.2.1120738687.1551393611; AMCV_7ADA401053CCF9130A490D4C%40AdobeOrg=-227196251%7CMCIDTS%7C17955%7CMCMID%7C64697849960691199666275063761991103277%7CMCAAMLH-1551905015%7C6%7CMCAAMB-1551998411%7CRKhpRz8krg2tLO6pguXWp5olkAcUniQYPHaMWWgdJ3xzPWQmdj0y%7CMCOPTOUT-1551400811s%7CNONE%7CMCAID%7CNONE; AKA_A2=A; QSI_HistorySession=https%3A%2F%2Fwww.adidas.fr%2Fchaussure-marquee-boost-low%2FD96931~1551395381504; BVBRANDID=f7196492-3073-4e5a-bdba-b7ad8d4b492f; BVBRANDSID=441363fc-6e08-41d9-832f-5f37306c4d78; s_sess=%5B%5BB%5D%5D; UserSignUpAndSave=9; cvPT=PDPF; cvOptiProfile={"MEN":5,"LVG":"M","ORIGINALS":1}; utag_main=v_id:016930b2be240007d5b76b63a05003079002f0710093c$_sn:2$_se:37$_ss:0$_st:1551398225189$ses_id:1551393610378%3Bexp-session$_pn:5%3Bexp-session$_prevpage:PRODUCT%7CCHAUSSURE%20CONTINENTAL%2080%20(G27706)%3Bexp-1551399829017; s_pers=%20pn%3D1%7C1553892221470%3B%20s_vnum%3D1554069600858%2526vn%253D1%7C1554069600858%3B%20s_visit%3D1%7C1551398225203%3B%20s_invisit%3Dtrue%7C1551398225207%3B; s_tps=1044; s_pvs=7839; RT="z=1&dm=adidas.fr&si=d8e519c6-91c3-4e88-b7b4-43d78ee7e898&ss=jsp7p3dc&sl=7&tt=5nj&bcn=%2F%2F36c3fef2.akstat.io%2F&ul=1ol9n&hd=1olen"' -H 'alexatoolbar-alx_ns_ph: AlexaToolbar/alx-4.0.3' -H 'accept-language: fr-FR,fr;q=0.9,en-US;q=0.8,en;q=0.7,ru;q=0.6,zh-TW;q=0.5,zh;q=0.4' -H 'x-instana-t: b912c9358e1342ba' -H 'x-instana-l: 1' -H 'content-type: application/json' -H 'accept: */*' -H 'user-agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_2) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/72.0.3626.119 Safari/537.36' -H 'referer: https://www.adidas.fr/chaussure-continental-80/G27706.html' -H 'authority: www.adidas.fr' -H 'accept-encoding: gzip, deflate, br' -H 'x-instana-s: 8f15af16a42aab3c' --compressed

        $curl_scraped_page = '';
        if ($proc = popen("($exec)2>&1", "r")) {
            while (!feof($proc)) $curl_scraped_page .= fgets($proc, 2000);
            pclose($proc);
        }

        // sleep(3);
        return $curl_scraped_page;
    }

    private function curlRequest($url) {
        $randIP = "" . mt_rand(0, 255) . "." . mt_rand(0, 255) . "." . mt_rand(0, 255) . "." . mt_rand(0, 255);
        $exec = 'curl -s -XGET -H \'cache-control: max-age=0\' -H \'accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8\' -H \'Accept-Language: en\' -H \'accept-encoding:  deflate, br\' -H \'User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_2) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/72.0.3626.119 Safari/537.36\' -H \'Content-type: text/html; charset=UTF-8\' -H \'cookie: geo_ip=' . $randIP . '; geo_country=FR; onesite_country=FR; akacd_plp_prod_adidas_grayling=3728753007~rv=96~id=dd8689155030c516831d7589576b7a70; bm_sz=233F1563FD253B83E90047C24676654C~YAAQRJM1VMqscjBpAQAAZ6qyMALUUvq+aH+3DrI5zxO4lzdtf7b86t4cwIilmWiR8BRqVyBJvoij37+OyQw3Mb+qzMlTbdy2YiyPj8l054HQn2E9NhNWVpApH01qDJy4+CYn6PUDOxo9ckZnNtLHMF6aHJ8TGFk41Q8HBV25nypa+wl/rtO7aH5aLIPLtw==; ab_monetate=a; MOBILE_ZOOM_ALERT_COOKIE=true; default_searchTerms_CustomizeSearch=%5B%5D; geoRedirectionAlreadySuggested=false; wishlist=%5B%5D; persistentBasketCount=0; UserSignUpAndSave=1; ab_optimizely=a; mt.v=5.1945272072.1551300211006; optimizelyEndUserId=oeu1551300211891r0.8896790619024113; cvPT=PLPT; akacd_phasedRC_Row=3728753010~rv=60~id=f1c251d43418f4df5130020b0de7c960; __adi_rt_DkpyPh8=CRTOH2H; inf_media_split=test; ab_decibel=a; RES_TRACKINGID=93583966541200315; ResonanceSegment=1; RES_SESSIONID=12637927541200315; _gcl_au=1.1.1909989029.1551300215; AMCVS_7ADA401053CCF9130A490D4C%40AdobeOrg=1; _ga=GA1.2.621567098.1551300216; _gid=GA1.2.93273394.1551300216; s_cc=true; AMCV_7ADA401053CCF9130A490D4C%40AdobeOrg=-227196251%7CMCIDTS%7C17955%7CMCMID%7C64697849960691199666275063761991103277%7CMCAAMLH-1551905015%7C6%7CMCAAMB-1551905015%7CRKhpRz8krg2tLO6pguXWp5olkAcUniQYPHaMWWgdJ3xzPWQmdj0y%7CMCOPTOUT-1551307416s%7CNONE%7CMCAID%7CNONE; QSI_HistorySession=https%3A%2F%2Fwww.adidas.fr%2Fchaussures-hommes%3Fstart%3D0~1551300216920; _scid=450709c0-4b51-4d94-a6b7-81296057cc38; notice_preferences=2:; notice_gdpr_prefs=0,1,2:; utag_main=v_id:016930b2be240007d5b76b63a05003079002f0710093c$_sn:1$_se:2$_ss:0$_st:1551302021437$ses_id:1551300214310%3Bexp-session$_pn:1%3Bexp-session$_prevpage:PLP%7CG_MEN%7CPR_SHOES%3Bexp-1551303814534; cvOptiProfile={"MEN":1,"LVG":"M"}; cmapi_gtm_bl=; cmapi_cookie_privacy=permit 1,2,3; _abck=76105D174578A5159651F34034206C3654359344142C000071F6765C52C4A775~0~F2FkoFKJWyV+jm7lCunCRmezDp7L9NYsWNV0A6IdiB8=~-1~-1; s_tps=692; s_pvs=45; s_pers=%20s_vnum%3D1551394800996%2526vn%253D1%7C1551394800996%3B%20s_visit%3D1%7C1551302021455%3B%20pn%3D1%7C1553892221470%3B%20s_invisit%3Dtrue%7C1551302331862%3B; RT="z=1&dm=adidas.fr&si=d8e519c6-91c3-4e88-b7b4-43d78ee7e898&ss=jsnndqqd&sl=1&tt=210&bcn=%2F%2F0211c84d.akstat.io%2F&ul=wgh7&hd=wgo1"\' "' . $url . '"';
        $proc = popen("($exec)2>&1", "r");

        $line = NULL;
        while (!feof($proc))
            $line .= fgets($proc, 4096);

        //sleep(1);

        // $f = fopen('pipi', "w+"); // открываем для записи
// пишем нашу строку и к ней добавляем раннее содержимое файла
        // fwrite($f, $line);

        return $line;
    }
}
<?php

namespace console\controllers;

use backend\helpers\StringHelper;
use backend\models\Process;
use common\models\Query;
use common\repositories\read\ShopsReadRepositories;
use console\extended\checkDirectory;
use console\helpers\Utils;
use console\interfaces\ShopInterface;
use console\repositories\mailRepositorie\sendMail;
use Yii;
use yii\base\Action;
use yii\console\Controller;

/**
 * Class NikeController
 * @package console\controllers
 * @property  yii\db\Connection $connection
 */
class NikeController extends Controller {
    const NAME_NIKE_MEN = 'nike_men';
    const NAME_NIKE_WOMEN = 'nike_women';
    const NAME_NIKE_GARCONES = 'nike_garcons';
    const NAME_NIKE_FILLES = 'nike_filles';

    private $checkDirectory;
    private $last_product_id;
    private $got_last_product_id;
    private $mailer;
    private $path_sql_path;

    //___Meta info for file
    private $start_product_id;
    private $shopReadRepositories;

    private $_name_directory = ''; //___ 158790909  //  1 raz prisvaivaem pri pervom sozdanii irarxii papok

    /**
     * NikeController constructor.
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
    }

//    /**
//     * @param Action $action
//     * @return bool
//     * @throws \Exception
//     */
//    public function beforeAction($action) {
//        if (!Utils::checkParseOrNot())
//            return false;
//        return parent::beforeAction($action);
//    }

    public function actionInsert() {
        $this->checkDirectory->init('nike');
    }

    /**
     * @throws \Exception
     */
    public function actionMakeListMen() {
        $this->scanForList(
            'https://store.nike.com/html-services/gridwallData?country=FR&lang_locale=fr_FR&gridwallPath=homme-chaussures/7puZoi3&pn=',
            self::NAME_NIKE_MEN);
    }


    /**
     * @throws \Exception
     */
    public function actionMakeListWomen() {
        $this->scanForList(
            'https://store.nike.com/html-services/gridwallData?country=FR&lang_locale=fr_FR&gridwallPath=femme-chaussures/7ptZoi3&pn=',
            self::NAME_NIKE_WOMEN);
    }

    /**
     * @throws \Exception
     */
    public function actionMakeListGarcons() {
        $this->scanForList(
            'https://store.nike.com/html-services/gridwallData?country=FR&lang_locale=fr_FR&gridwallPath=garçon-chaussures/7pvZoi3&pn=',
            self::NAME_NIKE_GARCONES);
    }

    /**
     * @throws \Exception
     */
    public function actionMakeListFilles() {
        $this->scanForList(
            'https://store.nike.com/html-services/gridwallData?country=FR&lang_locale=fr_FR&gridwallPath=girls-shoes7pwZoi3&pn=',
            self::NAME_NIKE_FILLES);
    }

    /**
     * @param $url //__ $url dmya parsinga lista
     * @param $category //__ $category (men, women m....)
     */
    private function scanForList($url, $category) {
        Utils::checkDirectoryForListProductCreated();

        try {
            Utils::checkScanList($category);
        }
        catch (\Exception $e) {
            Utils::log($e->getMessage(), 'nike');
            return;
        }

        $row_exists = Query::find()->where(['status' => ShopInterface::PROCESSING])->one();  //__ Stavit block block
        if ($row_exists['id']) {
            Utils::log('>>> Other query in processing. ' . $row_exists['explication'], 'nike');
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
            $shop_id = $this->shopReadRepositories->getIdByCategory($category);
            $fo = fopen(ShopInterface::LIST_SHOP_PRODUCTS . DIRECTORY_SEPARATOR . $category, 'w');

            $i = 0;
            while (true) {
                $i++;
                echo $url . $i . PHP_EOL;
                $curl_scraped_page = Utils::curlRequest($url . $i);
                if (!$curl_scraped_page)
                    continue;

                $data = json_decode($curl_scraped_page, true);

                Process::updateActivity($shop_id, Process::LIST_MAKE, $curl_scraped_page);
                $items = $data['sections'][0]['items'];

                for ($j = 0; $j < count($items); $j++)
                    if (isset($items[$j]['pdpUrl']) && false !== mb_stripos(trim($items[$j]['pdpUrl']), 'chaussure-', 0, 'UTF-8'))
                        fwrite($fo, trim($items[$j]['pdpUrl']) . PHP_EOL);

                if (!$data['nextPageDataService'])
                    break;
            }
            $transaction->commit();
            Query::deleteAll(['status' => Query::PROCESSING]);
        }
        catch (\Exception $e) {
            Utils::log('Get url for parsing. ' . $category . ' ' . $this->_name_directory . ' ' . $e->getMessage(), 'nike');
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
     * @throws \Exception
     */
    public function actionParsePageMen() {
        $this->parsePage(self::NAME_NIKE_MEN);
    }

    /**
     * @throws \Exception
     */
    public function actionParsePageWomen() {
        $this->parsePage(self::NAME_NIKE_WOMEN);
    }

    /**
     * @throws \Exception
     */
    public function actionParsePageGarcons() {
        $this->parsePage(self::NAME_NIKE_GARCONES);
    }

    /**
     * @throws \Exception
     */
    public function actionParsePageFilles() {
        $this->parsePage(self::NAME_NIKE_FILLES);
    }

    /**
     * @param $category
     * @throws yii\db\Exception
     */
    private function parsePage($category) {
        $path_to_list_file = ShopInterface::LIST_SHOP_PRODUCTS . DIRECTORY_SEPARATOR . $category;
        //__ Proveryaem suschestvovaniya etogo faila
        if (!file_exists($path_to_list_file) || !is_file($path_to_list_file)) {
            Utils::log('Net faila-spiska dlya ' . $category, 'nike');
            return;
        }

        $data = file_get_contents($path_to_list_file); //read the file
        $lines = explode(PHP_EOL, $data); //create array separate by new line
        array_pop($lines);

        $row_exists = Query::find()->where(['status' => ShopInterface::PROCESSING])->one();  //__ Stavit block block
        if ($row_exists['id']) {
            Utils::log('>>> Other query in processing. ' . $row_exists['explication'], 'nike');
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

            for ($i = 0; $i < count($lines); $i++) {
                echo $lines[$i].PHP_EOL;

                $curl_scraped_page = Utils::curlRequest($lines[$i]);
                if (!$curl_scraped_page)
                    continue;

                Process::updateActivity($shop_id, Process::PARSING_PRODUCT, $curl_scraped_page);

                $pattern = '/(?<=&pbid=).*\d/';
                preg_match_all($pattern, $lines[$i], $matches);

                $sql = '';
                if (count($matches[0])) { // Esli obuv s Nike Store
                    // preg_match_all('/(?<=\ <h1\ class="exp-pdp-title__main-title\ nsg-font-family--platform">)[\w\W]*?(?=<\/h1>)/', $curl_scraped_page, $matches_name);
                    preg_match_all('/(?<=js-pdpLocalPrice">)[\w\W]*?(?=<\/span>)/', $curl_scraped_page, $matches_price);

                    // $name = $matches_name[0][0];
                    $price = trim($matches_price[0][0]);
                    $price = substr($price, 0, -2);  //___ 160  Prosto chislo

                    switch (substr($price, -1)) {
                        case '€':
                            $currency = 1;
                            break;
                        case '$':
                            $currency = 2;
                            break;
                        case '£':
                            $currency = 3;
                            break;
                    }

                    preg_match_all("#(?<=type=\"importModelTempData\"\ data-name=\"pdpData\">)[\w\W]*?\"showRecyclingMessage\":true}#", $curl_scraped_page, $script);
                    $peremennaya = json_decode($script[0][0]);

                    $name = $peremennaya->productTitle;
                    $capacityMessage = $peremennaya->capacityMessage;

                    $exist_pbid = $db->createCommand('SELECT * FROM shoes_copy WHERE pbid ="' . $matches[0][0] . '"')->execute();

                    if ($exist_pbid['id']) { //___ Esli est takoi magazin u menya v BD
                        $sql .= '/* ' . $exist_pbid["product_id"] . ' ' . $lines[$i] . ' */' . PHP_EOL;
                        $sql .= 'UPDATE shoes_copy SET delivery= "' . substr($capacityMessage, 0, 200) . '" price = ' . (float) $price . ', updated_at = ' . time() . ' WHERE product_id = ' . $exist_pbid["product_id"] . ';' . PHP_EOL;
                    }
                    else {
                        //   print_r($peremennaya->prebuilds);
                        $sql .= '/* ' . $this->last_product_id . ' ' . $lines[$i] . ' */' . PHP_EOL;
                        $sql .= 'INSERT INTO product_copy (id, shop_id, type_id, created_at, updated_at) VALUES (' . $this->last_product_id . ', 1, ' . $shop_id . ', ' . $timeStampImages . ', ' . $timeStampImages . ');' . PHP_EOL;

                        //___ Kartinki START  ________
                        $sql_images = '';
                        $pict_my = $peremennaya->prebuilds;
                        $pic_outs = [];
                        for ($e = 0; $e < count($pict_my); $e++) {
                            if ($pict_my[$e]->pbid == $matches[0][0])
                                for ($d = 0; $d < sizeof($pict_my[$e]->views); $d++)
                                    array_push($pic_outs, $pict_my[$e]->views[$d]->url);
                        }

                        if (count($pic_outs)) { //__ Esli voobsche est kartinki to skachivaem ix  + dobavlyaem v bazu
                            for ($pi = 0; $pi < count($pic_outs); $pi++) {
                                $name_file = StringHelper::randomString(20, null, true);
                                if (Utils::saveImages($pic_outs[$pi], $this->_name_directory, $name_file))  //__ Esli kartinka est
                                    $sql_images .= '(' . $this->last_product_id . ', "' . $name_file . '.jpg", ' . $timeStampImages . ', ' . $timeStampImages . '),';
                            }

                            if (strlen($sql_images)) {
                                $sql_images = substr($sql_images, 0, -1);
                                $sql .= 'INSERT INTO images_copy (product_id, patch, created_at, updated_at)  VALUES ' . $sql_images . ';' . PHP_EOL;
                            }
                        }
                        //___ Kartinki END  ________

                        $this->last_product_id++;  //__ Delaem prirascheni id dlya vstavki v BD
                    }
                }
                else {  //___ Esli NE obuv s Nike Store
                    preg_match_all("#(?<=<script>window\.INITIAL_REDUX_STATE=)[\w\W]*?(?=;</script>)#", $curl_scraped_page, $script);

                    if (isset($script[0][0]) AND !empty($script[0][0])) {
                        $peremennaya = json_decode($script[0][0]);
                        preg_match('/(?<=">Article\ :\ )[\w\W]*?(?=<\/li>)/', $curl_scraped_page, $article);

                        if (!count($article))
                            continue;

                        $article_json = $peremennaya->Threads->products->{$article[0]};
                        $brand = $article_json->brand;
                        $sex = ($article_json->genders == "MEN") ? 0 : 1;

                        $search = 'u002F';
                        $replace = '';
                        $curl_scraped_page = str_replace($search, $replace, $curl_scraped_page);

                        preg_match('/(?<=data-test="product-title">).*?(?=<\/h1>)/', $curl_scraped_page, $name);
                        preg_match('/(?<=="product-sub-title">).*?(?=<\/h2>)/', $curl_scraped_page, $sub_name);
                        preg_match('/(?<=Couleur\ affichée\ :\ )[\w\W]*?(?=<\/li>)/', $curl_scraped_page, $color);
                        preg_match('/(?<=css-qnptk2"><p>).*(?=\.<\/p><ul\ class="description-preview__features\ )/', $curl_scraped_page, $description);
                        preg_match('/(?<=data-test="product-price">).*?(?= €<\/div>)/', $curl_scraped_page, $price);
                        $price = (float) str_replace(',', '.', $price[0]);

                        if (is_array($description) && count($description))
                            $description = StringHelper::slashEscape($description[0]);
                        else
                            $description = '';

                        preg_match_all('/(?<=data-css-ikkzrh="">EU\ ).*?(?=<\/label>)/', $curl_scraped_page, $sizes);

                        $row_exist = $db->createCommand('SELECT s.article, s.id, product_id FROM shoes_copy s INNER JOIN product_copy pr ON (pr.id = s.product_id) WHERE s.article = "' . $article[0] . '";')->queryOne();

                        if ($row_exist['id']) { // __ Esli u nas est yje takoi produkt v BD
                            $sql .= '/* ' . $row_exist['product_id'] . '  ' . $lines[$i] . ' */' . PHP_EOL;

                            $sql .= 'DELETE FROM size_copy WHERE product_id = ' . $row_exist["product_id"] . ';' . PHP_EOL;
                            $sql_size = '';
                            //  $size_json = $article_json->skus[0]->localizedSize;
                            for ($fu = 0; $fu < count($article_json->skus); $fu++)
                                $sql_size .= "(" . $row_exist["product_id"] . ", '" . $article_json->skus[$fu]->localizedSize . "', " . $timeStampImages . ", " . $timeStampImages . "),";

                            if (!strlen($sql_size))
                                continue;

                            $sql_size = substr($sql_size, 0, -1);
                            $sql .= 'INSERT INTO size_copy (product_id, size, created_at, updated_at) VALUES ' . $sql_size . ';' . PHP_EOL;

                            $sql .= 'UPDATE shoes_copy SET price = ' . (float) str_replace(',', '.', $article_json->fullPrice) . ', updated_at = ' . $timeStampImages . ' WHERE product_id = ' . $row_exist["product_id"] . ';' . PHP_EOL;
                        }
                        else {  //__ Vooobsche net
                            $this->got_last_product_id = true;
                            $sql .= '/* ' . $this->last_product_id . '  ' . $lines[$i] . ' */' . PHP_EOL;
                            $sql .= 'INSERT INTO product_copy (id, shop_id, type_id, created_at, updated_at) VALUES (' . $this->last_product_id . ', ' . $shop_id . ', 1, ' . $timeStampImages . ', ' . $timeStampImages . ');' . PHP_EOL;

                            $name[0] = StringHelper::slashEscape($name[0]);
                            $sub_name[0] = StringHelper::slashEscape($sub_name[0]);
                            $brand = StringHelper::slashEscape($brand);

                            switch ($article_json->currency) {
                                case  'EUR' :
                                    $currency = 1;
                                    break;
                                case 'US':
                                    $currency = 2;
                                    break;
                                case '£':
                                    $currency = 3;
                                    break;
                            }

                            $sql .= 'INSERT INTO shoes_copy (product_id, name, sub_name, currency, url, sex, brand, description, color, price, article, created_at, updated_at) VALUES (' . $this->last_product_id . ', "' . $name[0] . '", "' . $sub_name[0] . '", ' . $currency . ', "' . trim($lines[$i]) . '", ' . $sex . ', "' . $brand . '", "' . $description . '", "' . $color[0] . '", ' . $price . ', "' . $article[0] . '", ' . time() . ', ' . time() . ');' . PHP_EOL;

                            //___  Razmeri START
                            $sql_size = '';
                            for ($f = 0; $f < count($article_json->skus); $f++)
                                $sql_size .= '(' . $this->last_product_id . ', "' . $article_json->skus[$f]->localizedSize . '", ' . time() . ', ' . time() . '),';

                            if (!strlen($sql_size))
                                continue;

                            $sql_size = substr($sql_size, 0, -1);
                            $sql .= 'INSERT INTO size_copy (product_id, size, created_at, updated_at) VALUES ' . $sql_size . ';' . PHP_EOL;

                            //___  Razmeri STOP

                            //___ Kartinki START  ________
                            $sql_images = '';
                            $pict_my = $article_json->nodes[0]->nodes;

                            $pic_outs = [];
                            for ($e = 0; $e < count($pict_my); $e++) {
                                if ($pict_my[$e]->subType == 'image' && $pict_my[$e]->properties->portraitURL) {
                                    $full_size_pic = str_replace('t_default', 't_PDP_1280_v1', $pict_my[$e]->properties->portraitURL);
                                    array_push($pic_outs, $full_size_pic);
                                }
                            }

                            if (count($pic_outs)) { //__ Esli voobsche est kartinki to skachivaem ix  + dobavlyaem v bazu
                                for ($pi = 0; $pi < count($pic_outs); $pi++) {
                                    $name_file = StringHelper::randomString(20, null, true);
                                    if (Utils::saveImages($pic_outs[$pi], $this->_name_directory, $name_file)) //__ Esli kartinka est
                                        $sql_images .= '(' . $this->last_product_id . ', "' . $name_file . '.jpg", ' . $timeStampImages . ', ' . $timeStampImages . '),';
                                }

                                if (strlen($sql_images)) {
                                    $sql_images = substr($sql_images, 0, -1);
                                    $sql .= 'INSERT INTO images_copy (product_id, patch, created_at, updated_at)  VALUES ' . $sql_images . ';' . PHP_EOL;
                                }
                            }
                            //___ Kartinki END ________

                            $this->last_product_id++;  //__ Delaem prirascheni id dlya vstavki v BD
                        }
                    }
                    else continue;
                }

                $fp_add = fopen($this->path_sql_path, 'a');
                fwrite($fp_add, $sql);
                fclose($fp_add);
            }

            $this->last_product_id++;
            $this->checkDirectory->reconstructionSqlFile($this->path_sql_path, $this->start_product_id, $this->last_product_id);
            Utils::log('>>> Nike zakoncheno <<<', 'nike');
            $db->createCommand('DELETE FROM ' . Query::tableName() . ' WHERE name_shop = "' . $category . '"')->execute();
        }
        catch (\Exception $e) {
            Utils::log('Error of Generation sql file. ' . $category . '. ' . $this->_name_directory . '. ' . $e->getLine() . ' - ' . $e->getMessage(), 'nike');
//            $this->mailer->sendEmailGeneralError('Error of Generation sql file. ' . $category . '. ' . $this->_name_directory, $e->getLine() . ', ' . $e->getMessage() . PHP_EOL . $e->getTraceAsString());
            $db->createCommand('DELETE FROM ' . Query::tableName() . ' WHERE name_shop = "' . $category . '" ')->execute();
        }
    }
}
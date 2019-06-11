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
use Symfony\Component\DomCrawler\Crawler;
use Yii;
use yii\console\Controller;

/**
 * Class ZalandoController
 * @package console\controllers
 * @property  yii\db\Connection $connection
 */
class ZalandoController extends Controller {
    const NAME_MEN = 'zalando_men';
    const NAME_WOMEN = 'zalando_women';
    const NAME_GARCONES = 'zalando_garcons';
    const NAME_FILLES = 'zalando_filles';

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
     * ZalandoController constructor.
     * @param string $id
     * @param $module
     * @param sendMail $mailer
     * @param checkDirectory $checkDirectory
     * @param ShopsReadRepositories $shopReadRepositories
     * @param array $config
     */
    public function __construct($id, $module,
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

//    public function beforeAction($action) {
//        if (!Utils::checkParseOrNot())
//            return false;
//        return parent::beforeAction($action);
//    }

    public function actionInsert() {
        $this->checkDirectory->init('zalando');
    }

    /**
     * @throws \Exception
     */
    public function actionMakeListMen() {
        $this->scanForList(
            'https://www.zalando.fr/chaussures-homme/?p=',
            self::NAME_MEN);
    }

    /**
     * @throws \Exception
     */
    public function actionMakeListWomen() {
        $this->scanForList(
            'https://www.zalando.fr/chaussures-femme/?p=',
            self::NAME_WOMEN);
    }

    /**
     * @throws \Exception
     */
    public function actionMakeListGarcons() {
        $this->scanForList(
            'https://www.zalando.fr/chaussures-enfant/?gender=17&p=',
            self::NAME_GARCONES);
    }

    /**
     * @throws \Exception
     */
    public function actionMakeListFilles() {
        $this->scanForList(
            'https://www.zalando.fr/chaussures-enfant/?gender=18&p=',
            self::NAME_FILLES);
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
            Utils::log($e->getMessage(), 'zalando');
            return;
        }

        $row_exists = Query::find()->where(['status' => ShopInterface::PROCESSING])->one();  //__ Stavit block block
        if ($row_exists['id']) {
            Utils::log('>>> Other query in processing. ' . $row_exists['explication'], 'zalando');
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

            $pages = 2;
            for ($i = 1; $i <= $pages; $i++) {
                echo $url . $i . PHP_EOL;
                if (!$curl_scraped_page = Utils::curlRequest($url . $i))
                    continue;

                Process::updateActivity($shop_id, Process::LIST_MAKE, $curl_scraped_page);
                $document = new Crawler($curl_scraped_page);

                if ($i === 1 || $pages === 2) {
                    $pag = $document->filter('.cat_label-2W3Y8');
                    if (count($pag)) {
                        preg_match_all('/\d+/', $pag->eq(0)->text(), $matches);
                        $pages = (int) $matches[0][1];
                    }
                }

                $links = $document->filter('z-grid-item.cat_articleCard-1r8nF a.cat_imageLink-OPGGa');
                foreach ($links as $link)
                    fwrite($fo, 'https://www.zalando.fr' . trim($link->getAttribute('href')) . PHP_EOL);
            }

            $transaction->commit();
            Query::deleteAll(['status' => Query::PROCESSING]);
        }
        catch (\Exception $e) {
            Utils::log('Get url for parsing. ' . $category . ' ' . $this->_name_directory . ' ' . $e->getMessage(), 'zalando');
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
        $this->parsePage(self::NAME_MEN);
    }

    /**
     * @throws \Exception
     */
    public function actionParsePageWomen() {
        $this->parsePage(self::NAME_WOMEN);
    }

    /**
     * @throws \Exception
     */
    public function actionParsePageGarcons() {
        $this->parsePage(self::NAME_GARCONES);
    }

    /**
     * @throws \Exception
     */
    public function actionParsePageFilles() {
        $this->parsePage(self::NAME_FILLES);
    }

    /**
     * @param $category
     * @throws yii\db\Exception
     */
    private function parsePage($category) {
        $path_to_list_file = ShopInterface::LIST_SHOP_PRODUCTS . DIRECTORY_SEPARATOR . $category;
        if (!file_exists($path_to_list_file) || !is_file($path_to_list_file)) {
            Utils::log('Net faila-spiska dlya ' . $category, 'zalando');
            return;
        }

        $data = file_get_contents($path_to_list_file); //read the file
        $lines = explode(PHP_EOL, $data); //create array separate by new line
        array_pop($lines);

        $row_exists = Query::find()->where(['status' => ShopInterface::PROCESSING])->one();  //__ Stavit block block
        if ($row_exists['id']) {
            Utils::log('>>> Other query in processing. ' . $row_exists['explication'], 'zalando');
            return;
        }

        $query = new Query();
        $query->name_shop = $category;
        $query->status = ShopInterface::PROCESSING;
        $query->explication = 'Kachaem iz saita infu (kartinki + sql fail). Shop = ' . $category;
        $query->save();  // @todo aktivirovat

        $db = Yii::$app->db;
        try {
            $max_id = $db->createCommand('SELECT MAX(id) AS id FROM product_copy')->queryScalar() + 1;
            $list_shop_directory = Utils::scanDir(ShopInterface::DIR_STATIC);
            if (!$list_shop_directory)
                $this->last_product_id = $max_id;
            else {
                $array_shop_plus_id_number_start = $this->checkDirectory->check($list_shop_directory, 'end');
                rsort($array_shop_plus_id_number_start);
                $id_for_read_sql = array_values($array_shop_plus_id_number_start);

                if ($id_start_insert = array_shift($id_for_read_sql))
                    $this->last_product_id = $id_start_insert > $max_id ? $id_start_insert : $max_id;
                else
                    $this->last_product_id = $max_id;
            }

            $this->start_product_id = $this->last_product_id;
            if (!$this->_name_directory = Utils::prepareDirectory($category))
                throw new \Exception('Не удалось создать timestamp папку');

            $this->path_sql_path = $this->_name_directory . DIRECTORY_SEPARATOR . $category . '_sql.sql';
            $this->got_last_product_id = false; // ___ flag dlya opredeleniya togo esli mi yje vstavili ili bet SET @product_id= v faile sql
            $timeStamp_images = time();
            $shop_id = $this->shopReadRepositories->getIdByCategory($category);

            for ($i = 0; $i < count($lines); $i++) {
                $data = [];
                echo $lines[$i] . PHP_EOL;
                if (!$curl_scraped_page = Utils::curlRequest(trim($lines[$i])))
                    continue;

                Process::updateActivity($shop_id, Process::PARSING_PRODUCT, $curl_scraped_page);

                $document = new Crawler($curl_scraped_page);
                $cdata = $document->filter('#z-vegas-pdp-props');
                $name = $document->filter('#z-pdp-topSection .h-product-title h1.h-text');
                unset($document);

                if (!$name = trim($name->text()) || !count($cdata))
                    continue;

                $cdata = json_decode(str_replace(['<![CDATA[', ']]>'], '', htmlspecialchars_decode($cdata->eq(0)->text())), true);
                $product = $cdata['model']['articleInfo'];
                unset($cdata);

                $sizes = [];
                for ($j = 0; $j < count($product['units']); $j++)
                    $sizes[] = trim($product['units'][$j]['size']['local']);

                if (!count($sizes))
                    continue;

                $data['brand'] = StringHelper::slashEscape(trim($product['brand']['name']));
                $data['name'] = $name;
                $data['color'] = StringHelper::slashEscape(trim($product['color']));
                $data['price'] = (float) str_replace(',', '.', $product['displayPrice']['price']['value']);
                switch ($product['displayPrice']['price']['currency']) {
                    case 'EUR':
                        $data['currency'] = 1;
                        break;
                    case 'US':
                        $data['currency'] = 2;
                        break;
                    case '£':
                        $data['currency'] = 3;
                        break;
                }
                $data['url'] = trim($lines[$i]);
                $data['sex'] = $category === self::NAME_MEN ? 0 : 1;

                $images = [];
                for ($j = 0; $j < count($product['media']['images']); $j++)
                    $images[] = trim($product['media']['images'][$j]['sources']['zoom']);

                $attributes = $product['attributes'];
                for ($j = 0; $j < count($attributes[0]['data']); $j++) {
                    if (array_key_exists('name', $attributes[0]['data'][$j]) && isset($attributes[0]['data'][$j]['name'])) {
                        if (false !== mb_stripos(trim($attributes[0]['data'][$j]['name']), 'Dessus', 0, 'UTF-8'))
                            $data['dessus'] = StringHelper::slashEscape(trim($attributes[0]['data'][$j]['values']));
                        elseif (false !== mb_stripos(trim($attributes[0]['data'][$j]['name']), 'Doublure', 0, 'UTF-8'))
                            $data['doublure'] = StringHelper::slashEscape(trim($attributes[0]['data'][$j]['values']));
                        elseif (false !== mb_stripos(trim($attributes[0]['data'][$j]['name']), 'propre', 0, 'UTF-8'))
                            $data['semelle_de_proprete'] = StringHelper::slashEscape(trim($attributes[0]['data'][$j]['values']));
                        elseif (false !== mb_stripos(trim($attributes[0]['data'][$j]['name']), "d'usure", 0, 'UTF-8'))
                            $data['semelle_usure'] = StringHelper::slashEscape(trim($attributes[0]['data'][$j]['values']));
                    }
                }
                for ($j = 0; $j < count($attributes[1]['data']); $j++) {
                    if (array_key_exists('name', $attributes[1]['data'][$j]) && isset($attributes[1]['data'][$j]['name'])) {
                        if (false !== mb_stripos(trim($attributes[1]['data'][$j]['name']), 'Fermeture', 0, 'UTF-8'))
                            $data['fermeture'] = StringHelper::slashEscape(trim($attributes[1]['data'][$j]['values']));
                        elseif (false !== mb_stripos(trim($attributes[1]['data'][$j]['name']), 'Motif', 0, 'UTF-8'))
                            $data['motif'] = StringHelper::slashEscape(trim($attributes[1]['data'][$j]['values']));
                    }
                }
                $data['reference'] = trim($product['id']);

                unset($attributes);
                unset($product);

                $sql = '';
                $row_exist = $db->createCommand('SELECT s.reference, s.id, product_id FROM shoes_copy s INNER JOIN product_copy pr ON (pr.id = s.product_id) WHERE s.reference = "' . $data['reference'] . '";')->queryOne();
                $fd = fopen('test_reference', 'a');
                fwrite($fd, $data['reference'] . ' ---> ' . $row_exist['reference']);
                fclose($fd);

                if ($row_exist['id']) { // __ Esli u nas est yje takoi produkt v BD
                    $sql .= '/* ' . $row_exist['product_id'] . '  ' . $lines[$i] . ' */' . PHP_EOL;

                    $sql .= 'DELETE FROM size_copy WHERE product_id = ' . $row_exist["product_id"] . ';' . PHP_EOL;

                    $sql_size = '';
                    for ($j = 0; $j < count($sizes); $j++)
                        $sql_size .= '(' . $row_exist["product_id"] . ', "' . $sizes[$j] . '", ' . $timeStamp_images . ', ' . $timeStamp_images . '),';

                    $sql_size = substr($sql_size, 0, -1);
                    $sql .= 'INSERT INTO size_copy (product_id, size, created_at, updated_at) VALUES ' . $sql_size . ';' . PHP_EOL;
                    $sql .= 'UPDATE shoes_copy SET price = ' . $data['price'] . ', updated_at = ' . $timeStamp_images . ' WHERE product_id = ' . $row_exist["product_id"] . ';' . PHP_EOL;
                }
                else {  //__ Vooobsche net
                    $this->got_last_product_id = true;
                    $sql .= '/* ' . $this->last_product_id . '  ' . $lines[$i] . ' */' . PHP_EOL;
                    $sql .= 'INSERT INTO product_copy (id, shop_id, type_id, created_at, updated_at) VALUES (' . $this->last_product_id . ', ' . (int) $shop_id . ', 1, ' . $timeStamp_images . ', ' . $timeStamp_images . ');' . PHP_EOL;

                    if (count($data)) {
                        $fields = '';
                        $values = '';
                        foreach ($data as $k => $v) {
                            $fields .= $k . ', ';
                            if (is_string($v))
                                $values .= '"' . $v . '", ';
                            else
                                $values .= $v . ', ';
                        }

                        $sql .= 'INSERT INTO shoes_copy (product_id, ' . $fields .  'created_at, updated_at) VALUES (' . $this->last_product_id . ', ' . $values . time() . ', ' . time() . ');' . PHP_EOL;
                    }

                    //___  Razmeri START
                    $sql_size = '';
                    for ($j = 0; $j < count($sizes); $j++)
                        $sql_size .= '(' . $this->last_product_id . ', "' . $sizes[$j] . '", ' . time() . ', ' . time() . '),';

                    $sql_size = substr($sql_size, 0, -1);
                    $sql .= 'INSERT INTO size_copy (product_id, size, created_at, updated_at) VALUES ' . $sql_size . ';' . PHP_EOL;
                    //___  Razmeri STOP

                    //___ Kartinki START  ________

                    //  echo "Koluchestvo kartinok = " . sizeof($pic_outs) . PHP_EOL;
                    if (count($images)) { //__ Esli voobsche est kartinki to skachivaem ix  + dobavlyaem v bazu
                        $sql_images = '';
                        for ($j = 0; $j < count($images); $j++) {
                            $name_file = StringHelper::randomString(20, null, true);

                            if (Utils::saveImages($images[$j], $this->_name_directory, $name_file)) //__ Esli kartinka est
                                $sql_images .= '(' . $this->last_product_id . ', "' . $name_file . '.jpg", ' . $timeStamp_images . ', ' . $timeStamp_images . '),';
                        }

                        if (strlen($sql_images)) {
                            $sql_images = substr($sql_images, 0, -1);
                            $sql .= 'INSERT INTO images_copy (product_id, patch, created_at, updated_at)  VALUES ' . $sql_images . ';' . PHP_EOL;
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
            Utils::log('>>> Zalando zakoncheno <<<', 'zalando');
            $db->createCommand('DELETE FROM ' . Query::tableName() . ' WHERE name_shop = "' . $category . '"')->execute();
        }
        catch (\Exception $e) {
            Utils::log('Error of Generation sql file. ' . $category . '. ' . $this->_name_directory . '. ' . $e->getLine() . ' - ' . $e->getMessage(), 'zalando');
//            $this->mailer->sendEmailGeneralError('Error of Generation sql file. ' . $category . '. ' . $this->_name_directory, $e->getLine() . ', ' . $e->getMessage() . PHP_EOL . $e->getTraceAsString());
            // $this->mailer->sendEmailGeneralError(); // Mojno sdelat uvedomleniya na email o zonche vipolenniya zadachi TUT !
            $db->createCommand('DELETE FROM ' . Query::tableName() . ' WHERE name_shop = "' . $category . '" ')->execute();
        }
    }
}
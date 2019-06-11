<?php

namespace console\controllers;

use console\extended\AdvizorParser;
use console\helpers\Utils;
use yii\console\Controller;

class AdvizorController extends Controller{
    const PARSE_LIST_DIR = 'archive/advizor/';

    public function __construct($id, $module, $config = []) {
        parent::__construct($id, $module, $config);
    }

    public function actionParseLinks($country, $search) {
        $file_name = self::PARSE_LIST_DIR . $country . '-' . $search . '.txt';
        $parse_cities = null;
        if (file_exists($file_name))
            $parse_cities = explode(PHP_EOL, file_get_contents($file_name));

        $db = \Yii::$app->db1;
        $cities = $db->createCommand('select city_id from cities where country_id = ' . (int) $country)->queryAll();
        $db->createCommand('SET SESSION wait_timeout = 600;')->execute();

        $parser = new AdvizorParser($country, $search);
        $fo = fopen($file_name, 'a');
        foreach ($cities as $city) {
            if ($parse_cities && in_array($city['city_id'], $parse_cities))
                continue;

            $transaction = $db->beginTransaction();
            try {
                $links = $parser->parse_page_links($city['city_id']);
                if (count($links))
                    for ($i = 0; $i < count($links); $i++)
                        $db->createCommand('insert ignore into geo (country_id, city_id, url) values (:country, :city, :url)', $links[$i])->execute();

                fwrite($fo, $city['city_id'] . PHP_EOL);
                $transaction->commit();
            }
            catch (\Exception $e) {
                Utils::log($e->getMessage(), 'advizor');
                $transaction->rollBack();
            }
        }
        fclose($fo);
        Utils::log('Парсер закончил парсинг ссылок. страна: ' . $country . '. название ресторана: ' . $search, 'advizor');
    }

    public function actionParsePage() {
        $parser = new AdvizorParser();
        $db = \Yii::$app->db1;
        $db->createCommand('SET SESSION wait_timeout = 600;')->execute();
        $urls = $db->createCommand('select id, url, counter from geo where flag = 2 and counter < 3 limit 100')->queryAll();
        if (count($urls)) {
            $data = $parser->parse_page($urls);
            $transaction = $db->beginTransaction();
            try {
                for ($i = 0; $i < count($data); $i++)
                    $db->createCommand()->update('geo', $data[$i]['data'], 'id = ' . $data[$i]['id'])->execute();
                $transaction->commit();
            }
            catch (\Exception $e) {
                Utils::log($e->getMessage(), 'advizor');
                $transaction->rollBack();
            }
        }
        else
            Utils::log('Нет url в базе для парсинга страниц', 'advizor');
    }
}
<?php

namespace console\helpers;

use console\interfaces\ShopInterface;
use console\repositories\mailRepositorie\sendMail;
use Yii;
use yii\db\Exception;

class Utils {
    const LOG_DIR = '/var/www/archive/';

    public static function log($str, $file_name, sendMail $mailer = null) {
        $log = '[' . date('Y-m-d H:i:s') . '] ' . $str . PHP_EOL;

        if ($mailer)
            $mailer->sendEmailGeneralError('Error!', $log);

        $fo = fopen(self::LOG_DIR . $file_name . '.log', 'a');
        fwrite($fo, $log);
        fclose($fo);
    }

    /**
     * @return bool
     * @throws Exception
     */
    public static function checkParseOrNot() {
        $db = Yii::$app->db;
        $settings = $db->createCommand('SELECT * FROM settings')->queryOne();
        return $settings['image_parse_on_off'];
    }

    public static function checkDirectoryForListProductCreated() {
        echo ShopInterface::LIST_SHOP_PRODUCTS . "\n";
        if (!file_exists(ShopInterface::LIST_SHOP_PRODUCTS) && !is_dir(ShopInterface::LIST_SHOP_PRODUCTS))
            mkdir(ShopInterface::LIST_SHOP_PRODUCTS);
    }

    /**
     * @param $category
     * @throws \Exception
     */
    public static function checkScanList($category) {
        $list = self::scanDir(ShopInterface::LIST_SHOP_PRODUCTS);
        if (count($list) && in_array($category, $list))
            throw new \Exception('Попытка повторного парсинга ссылок категории ' . $category);
    }

    public static function metaTags($start_product_id, $last_product_id) {
        return '/* -START ID-' . $start_product_id . ' */' . PHP_EOL . '/* -END ID-' . (intval($last_product_id) - 1) . ' */' . PHP_EOL . '/* -END- */' . PHP_EOL;
    }

    /**
     * @param $dir
     * @return array
     * @throws \Exception
     */
    public static function scanDir($dir) {
        if (!is_dir($dir))
            throw new \Exception('Нет такой папки: ' . $dir);

        $list = scandir($dir, 1);
        unset($list[count($list) - 1], $list[count($list) - 1]);

        return $list;
    }

    /**
     * @param $path_to_shop
     * @param bool $return_list
     * @return array|bool
     * @throws \Exception
     */
    public static function checkDirectoryShop($path_to_shop, $return_list = false) {
        $list = self::scanDir($path_to_shop);
        if (count($list) >= ShopInterface::MAX_NUMBER_PER_DIRECTORY)
            return false;

        if ($return_list)
            return $list;
        return true;
    }

    /**
     * @param $category
     * @return string
     * @throws \Exception
     */
    public static function prepareDirectory($category) {
        if (!file_exists(ShopInterface::DIR_STATIC))
            mkdir(ShopInterface::DIR_STATIC);

        $dir_shop_name = ShopInterface::DIR_STATIC . DIRECTORY_SEPARATOR . $category;

        if (!file_exists($dir_shop_name))
            mkdir($dir_shop_name);

        if (!self::checkDirectoryShop($dir_shop_name))
            throw new \Exception('Popitka zozdaniya escho odnoi papki v ' . $dir_shop_name);

        //___ /var/www/learning/static/nike/images/156756798  Udalyam esli est takoi dlya izbejaniya problem. Na vsyakii sluchai
        $name_directory = $dir_shop_name . DIRECTORY_SEPARATOR . time();

        if (file_exists($name_directory) && is_dir($name_directory))
            self::removeDirectory($name_directory);  //__ esli skript zapuschen v odu i tu je sekndu

        mkdir($name_directory);
        fclose(fopen($name_directory . DIRECTORY_SEPARATOR . $category . '_sql.sql', 'x'));

        //___ /var/www/learning/static/nike/images
        if (!file_exists($name_directory . DIRECTORY_SEPARATOR . ShopInterface::IMAGES_DIRECTORY))
            mkdir($name_directory . DIRECTORY_SEPARATOR . ShopInterface::IMAGES_DIRECTORY);

        return $name_directory;
    }

    public static function saveImages($url, $path, $name_file) {
        $path = $path . DIRECTORY_SEPARATOR . ShopInterface::IMAGES_DIRECTORY;
        $content = self::curlRequest($url, true);

        if (!$content)
            return false;

        $first_directory = substr($name_file, 0, 2);
        $second_directory = substr($name_file, 2, 2);

        if (!file_exists($path . DIRECTORY_SEPARATOR . $first_directory))
            mkdir($path . DIRECTORY_SEPARATOR . $first_directory);

        if (!file_exists($path . DIRECTORY_SEPARATOR . $first_directory . DIRECTORY_SEPARATOR . $second_directory))
            mkdir($path . DIRECTORY_SEPARATOR . $first_directory . DIRECTORY_SEPARATOR . $second_directory);

        $path_to_save = $path . DIRECTORY_SEPARATOR . $first_directory . DIRECTORY_SEPARATOR . $second_directory . DIRECTORY_SEPARATOR . $name_file . '.jpg';

        $fp_image = fopen($path_to_save, 'x');
        fwrite($fp_image, $content);
        fclose($fp_image);

        return true;
    }

    public static function removeDirectory($dir) {
        if ($objs = glob($dir . "/*"))
            foreach ($objs as $obj)
                is_dir($obj) ? self::removeDirectory($obj) : unlink($obj);

        rmdir($dir);
    }

    /**
     * @param $url
     * @param bool $return_binary
     * @param array $headers
     * @return bool|string
     */
    public static function curlRequest($url, $return_binary = false, $headers = []) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, trim($url));
        curl_setopt($ch, CURLOPT_AUTOREFERER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch,  CURLOPT_MAXREDIRS, 5);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        if ($headers)
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        if ($return_binary)
            curl_setopt($ch, CURLOPT_BINARYTRANSFER, 1);

        $page = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if ($httpCode != 200)
            return false;

        return $page;
    }
}
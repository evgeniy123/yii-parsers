<?php

namespace console\extended;

use common\models\Exceptions;
use common\models\Query;
use common\models\Shops;
use console\helpers\ExceptionsLog;
use console\helpers\Utils;
use console\interfaces\ShopInterface;
use console\repositories\mailRepositorie\sendMail;
use SplFileObject;
use Yii;
use yii\db\Exception;
use yii\helpers\ArrayHelper;

class checkDirectory {
    private $mailer;
    private $name_shop;        //___ 'nike'
    private $path_to_shop;     //__ /var/www/learning/static/nike
    private $work_directory;  //__ 15897979
    private $new_updated;  //__ Sudya po tomu est li katalog dlya magazin v destination directory vistavlyaem znachenie
    private $count_images_insert = 0;  //__ Kolichestvo perenesennix kartinok
    private $time_execute_move_images;  //__ Vremya vipolneniya kartinok v papku nike
    private $time_execute_delete_images;  //__ Vremya na udaleniye kartinok
    private $total_images = 0;  //__ Total Images
    private $count_insert = 0;  //__ Total INSERT kotorie vipolnenie bili

    /**
     * ZalandoController constructor.
     * @param sendMail $mailer
     */
    public function __construct(sendMail $mailer) {
        $this->mailer = $mailer;

        if (!file_exists(ShopInterface::PUBLIC_IMAGE_DIR))
            mkdir(ShopInterface::PUBLIC_IMAGE_DIR);

        if (!file_exists(ShopInterface::DIR_STATIC))
            mkdir(ShopInterface::DIR_STATIC);
    }

    /**
     * @param $shop_name
     * @return bool
     */
    private function checkExceptionShop($shop_name) {
        //__ Iskluchaem katalogi v kotorie ne nujno zaxodit
        // tak kak tam chto to ne pravilno i ix nujn budet udalit posle manualnogo analiza
        $shop_directories_for_exclude = ArrayHelper::getColumn(Exceptions::find()->all(), 'name_shop');
        if (!in_array($shop_name, $shop_directories_for_exclude))
            return true;
        return false;
    }

    /**
     * @param $list_shop_directory
     * @param $start_stop
     * @return array
     * @throws \Exception
     */
    public function check($list_shop_directory, $start_stop) {
        $array_shop_plus_id_number_start = [];
        //__ Ubiraem iz massiva tex kto sidit v exceptions tablice
        $list_shop_directory = array_filter($list_shop_directory, [$this, 'checkExceptionShop']);

        for ($i = 0; $i < count($list_shop_directory); $i++) {
            $shop_path = ShopInterface::DIR_STATIC . DIRECTORY_SEPARATOR . $list_shop_directory[$i];
            $directories_shop_path = Utils::scanDir($shop_path);
            $dir_name = $directories_shop_path[0];

            if (!$dir_name) //__ Elsi ne nashel papki timestamp v papke magazina
                throw new \Exception('Net papki timestampt dlya ' . $shop_path);

            $this->name_shop = $list_shop_directory[$i];
            $this->checkStructureTimeStamp($shop_path . DIRECTORY_SEPARATOR . $dir_name);

            $sql_file_path = $shop_path . DIRECTORY_SEPARATOR . $dir_name . DIRECTORY_SEPARATOR . $this->name_shop . '_sql.sql';
            //__ Videlyaem iz nego pervoe znachenie
            $file_sql = new SplFileObject($sql_file_path);
            $file_sql->seek(($start_stop == 'start') ? 0 : 1); // переходим ко второй строке (нумерация с нуля)

            preg_match(($start_stop == 'start') ? '/(?<=\/\*\ -START\ ID-).*(?=\ \*\/)/' : '/(?<=\/\*\ -END\ ID-).*(?=\ \*\/)/', $file_sql->current(), $matches);

            if (empty($matches[0]) || !intval($matches[0])) {
                //__ Logika
                // 1. Esli v kakom to faile ne naideni START i END
                // 2. Smotrim esli li oshibka ta je(tot je magazin) yje v exeptions
                $find_same_error = Exceptions::findOne(['name_shop' => $this->name_shop, 'code_error' => Exceptions::END_NOT_FOUND]);
                if (!$find_same_error['id']) {
                    $log_error = new ExceptionsLog();
                    $log_error->insert(
                        Exceptions::ERROR,
                        $this->name_shop,
                        'Net v odnoi iz failov sql zagolovka. Posmotri fail ' . $sql_file_path . '. V nem nepravilnii sql !!!',
                        Exceptions::END_NOT_FOUND
                    );

                    throw new \Exception('Net v odnoi iz failov sql zagolovka. Posmotri fail ' . $sql_file_path . '. V nem nepravilnii sql !!!');
                }
            }

            $array_shop_plus_id_number_start += $array_shop_plus_id_number_start + [$this->name_shop => $matches[0]];
        }

        return $array_shop_plus_id_number_start;
    }

    public function reconstructionSqlFile($path_sql_path, $start_product_id, $last_product_id) {
        $fp_end_sql = fopen($path_sql_path, 'r');
        $text = fread($fp_end_sql, filesize($path_sql_path)); //читаем
        fclose($fp_end_sql);

        $meta_info = Utils::metaTags($start_product_id, $last_product_id);
        $f = fopen($path_sql_path, "w"); // открываем для записи
        // пишем нашу строку и к ней добавляем раннее содержимое файла
        fwrite($f, $meta_info . $text);
        fclose($f);
    }

    function sort_in_array($a, $b) {
        if ($a == $b)
            return 0;
        return ($a > $b) ? -1 : 1;
    }

    public function init($controller_name) {
        try {
            //__ Naxodim podxodyaschee imya papki iz kotoroi budem brat potom faili.
            $list_shop_directory = Utils::scanDir(ShopInterface::DIR_STATIC);
            //_ Esli net bolshe papok s magazinami
            if (!count($list_shop_directory))
                throw new \Exception('Net papok s magazinami. nike_men i tp');

            //__ Smotrim esli chto to seichas vstavlyaetsya
            $processing = Query::find()->where(['status' => Query::PROCESSING])->one();
            if ($processing['id'])
                throw  new \Exception('>>> Other insertion in processing ' . $processing['name_shop']);
        }
        catch (\Exception $e) {
            Utils::log($e->getMessage(), $controller_name);
//            $this->mailer->sendEmailGeneralError(
//                'Other insertion in processing',
//                'Other insertion in processing\n' . $processing['name_shop']);
            return;
        }

        $query = new Query();
        $query->name_shop = 'All';
        $query->status = ShopInterface::PROCESSING;
        $query->explication = 'Nachal vstavku sql i perenos images';
        $query->save();

        try {
            $array_shop_plus_id_number_start = $this->check($list_shop_directory, 'start');
            //__ Esli net niodnogo magazina(papki): nike_men, adidias_women i tp -> To generim oshibku
            if (!count($array_shop_plus_id_number_start))
                throw new \Exception('Net bolshe magazinov dlya parsinga');

            $name_shop_for_read_sql = array_keys($array_shop_plus_id_number_start);
            $shop_name_start_insert = array_pop($name_shop_for_read_sql);
            $exist = Shops::find()->where(['name' => $shop_name_start_insert])->scalar();

            if (!$exist)
                throw new \Exception('Net takogo magazina v BD !'); //___ Esli net takogo magazina zaregenovago v BD

            //__ Proveryaem chto papka s imenem magazina suschestvuem
            $this->path_to_shop = ShopInterface::DIR_STATIC . DIRECTORY_SEPARATOR . $shop_name_start_insert;
            $this->name_shop = $shop_name_start_insert;
            $exist_directory = file_exists($this->path_to_shop);

            if (!$exist_directory)
                throw new \Exception('Net failov dlya etogo magazina!');  //___ Esli nikto ne zakachal po FTP failov ranshe  - oshibka

            $list_dir_data = Utils::scanDir($this->path_to_shop);
            if (count($list_dir_data) > ShopInterface::MAX_NUMBER_PER_DIRECTORY)
                throw new \Exception('В папке магазина ' . $this->path_to_shop . ' больше папок, чем ожидалось');

            $this->checkDirectoryShop($list_dir_data);
        }
        catch (\Exception $e) {
            Utils::log($e->getMessage(), $controller_name);
//            $this->mailer->sendEmailGeneralError(
//                'Other insertion in processing',
//                'Other insertion in processing\n' . $processing['name_shop']);
        }
        finally {
            Query::deleteAll(['status' => Query::PROCESSING]);
        }
    }

    /**
     * @param $list_dir_data
     * @throws \Exception
     */
    private function checkDirectoryShop($list_dir_data) {
        if (count($list_dir_data)) {
            //__Proveryaem chto v papke tolko direktori i nichgo drugoe
            for ($i = 0; $i < count($list_dir_data); $i++) {
                if (!is_dir($this->path_to_shop . DIRECTORY_SEPARATOR . $list_dir_data[$i]))
                    throw new \Exception('Est kakoi to katalog kotorii ne katalog: ' . $this->path_to_shop . DIRECTORY_SEPARATOR . $list_dir_data[$i]);
            }

            //__ Videlyaem imaya papki poslednei papki
            $this->work_directory = $list_dir_data[0];

            for ($i = 1; $i < count($list_dir_data); $i++)
                Utils::removeDirectory($this->path_to_shop . DIRECTORY_SEPARATOR . $list_dir_data[$i]);

            //__ Proveryaem pravilnyu strukturu v samoi papke-Unixtime
            $this->checkStructureTimeStamp($this->path_to_shop . DIRECTORY_SEPARATOR . $this->work_directory);
            $image_directory_to = ShopInterface::PUBLIC_IMAGE_DIR;
            echo $this->path_to_shop . DIRECTORY_SEPARATOR . $this->work_directory . DIRECTORY_SEPARATOR . $this->name_shop . '_sql.sql';
            //__ Delat OBYAZATELNO snachalo sql zapros a potom tolko kartinki !!! Produmano yje
            $this->sqlExecute($this->path_to_shop . DIRECTORY_SEPARATOR . $this->work_directory . DIRECTORY_SEPARATOR . $this->name_shop . '_sql.sql');
            $start_move = microtime(true);
            $this->copy_folder($this->path_to_shop . DIRECTORY_SEPARATOR . $this->work_directory . DIRECTORY_SEPARATOR . ShopInterface::IMAGES_DIRECTORY, $image_directory_to);
            $this->time_execute_move_images = (microtime(true) - $start_move);
            $start_delete = microtime(true);
            Utils::removeDirectory($this->path_to_shop);  //__ Udalyaem iznachalnii katalog s failami
            $this->time_execute_delete_images = (microtime(true) - $start_delete);

//            $this->mailer->sendEmailStatistic(
//                $this->count_images_insert,
//                $this->time_execute_move_images,
//                $this->time_execute_delete_images,
//                $this->total_images,
//                $this->count_insert
//            );
        }
        else  //___ esli net failov-direktorii v papke to udalyaem pustoi katalog
            rmdir($this->path_to_shop);
    }

    /*
        /**
         * @param $image_directory_from
         * @param $image_directory_to
         */
    /*private function moveImages($image_directory_from, $image_directory_to)
    {
        echo $image_directory_from . PHP_EOL;
        echo $image_directory_to . PHP_EOL;

        if (is_dir($image_directory_to)) {
            if (is_writable($image_directory_to)) {
                if ($handle = opendir($image_directory_from)) {
                    while (false !== ($file = readdir($handle))) {
                        if (is_file($image_directory_from . '/' . $file)) {
                            rename($image_directory_from . '/' . $file, $image_directory_to . '/' . $file);
                            $this->count_images_insert++; //__ Privavlyaem na 1 kolichestov failo perekopirovanix
                        }
                    }
                    closedir($handle);
                } else {
                    $this->mailer->sendErrorMoveFiles($image_directory_from . 'could not be opened.\n');
                    // echo "$image_directory_from could not be opened.\n";
                }
            } else {
                $this->mailer->sendErrorMoveFiles($image_directory_to . 'is not writable!\n');
                //echo "$image_directory_to is not writable!\n";
            }
        } else {
            $this->mailer->sendErrorMoveFiles($image_directory_to . 'is not a directory!\n');
            // echo "$image_directory_to is not a directory!\n";
        }
    } */

    /**
     * @param string $d1
     * @param string $d2
     * @param bool $upd
     * @param bool $force
     */
    private function copy_folder($d1, $d2, $upd = true, $force = true) {
        if (is_dir($d1)) {
            $d2 = $this->mkdir_safe($d2, $force);
            if (!$d2)
                return;

            $d = dir($d1);
            while (false !== ($entry = $d->read())) {
                if ($entry != '.' && $entry != '..')
                    $this->copy_folder("$d1/$entry", "$d2/$entry", $upd, $force);
            }

            $d->close();
        }
        else {
            $ok = $this->copy_safe($d1, $d2, $upd);
            if ($ok) {
                $ok = "ok-- ";
                $this->count_images_insert++;
            }
            else
                $ok = " -- ";

            $this->total_images++;
            // $ok = ($ok) ? "ok-- " : " -- ";
        }
    } //function copy_folder

    private function mkdir_safe($dir, $force) {
        if (file_exists($dir)) {
            if (is_dir($dir)) return $dir;
            else if (!$force) return false;
            unlink($dir);
        }

        return (mkdir($dir, 0644, true)) ? $dir : false;
    } //function mkdir_safe

    private function copy_safe($f1, $f2, $upd) {
        $time1 = filemtime($f1);

        if (file_exists($f2)) {
            $time2 = filemtime($f2);
            if ($time2 >= $time1 && $upd) return false;
        }

        $ok = copy($f1, $f2);
        if ($ok) touch($f2, $time1);

        return $ok;
    } //function copy_safe

    /**
     * @param string $path_directory
     * @throws \Exception
     */
    private function checkStructureTimeStamp($path_directory) {
        //__ proveryaem est li images papka i eto papka
        if (!file_exists($path_directory . DIRECTORY_SEPARATOR . ShopInterface::IMAGES_DIRECTORY) || !is_dir($path_directory . DIRECTORY_SEPARATOR . ShopInterface::IMAGES_DIRECTORY))
            throw new \Exception('Direktoriya dlya kartinok  ' . $path_directory . DIRECTORY_SEPARATOR . ShopInterface::IMAGES_DIRECTORY . ' ne suschestvuet');

        //__ proveryaem esli pravilnii sql fail i eto ne directory
        if (!file_exists($path_directory . DIRECTORY_SEPARATOR . $this->name_shop . '_sql.sql') || is_dir($path_directory . DIRECTORY_SEPARATOR . $this->name_shop . '_sql.sql'))
            throw new \Exception('Fail  sql ' . $path_directory . DIRECTORY_SEPARATOR . $this->name_shop . '_sql.sql  ne suschestvuet');

        //__ Tut mi v papke 15897980.
        // Proveryaem esli failov > 4 -> oshibka tak kak doljna bit:
        // 1) 'images'
        // 2) ..sql
        // 3) .
        // 4) ..
        if (count(scandir($path_directory)) > 4)
            throw new \Exception('Mnogo failov ili papok. ' . $path_directory);
    }

    /**
     * @param string $sql_file
     * @throws \Exception
     */
    private function sqlExecute($sql_file) {
        $db = Yii::$app->db;
        //__ Chitaem fail sql i esli budut problemi so vstavkoi budet otkat
        $transaction = $db->beginTransaction();

        foreach ($this->generator($sql_file) as $line) {
            try {
              //  echo "V generatore";
                //__ Podschitivaem voobsche vsego INSERT posle kajdogo
                $findme = 'INSERT';
                $pos = stripos($line, $findme);  //__ Bez ucheta registra

                if ($pos === false)  //__ Obyazatelno sostavit tut false !!!
                    $this->count_insert++;

                if (!empty($line))
                    $db->createCommand(str_replace(array("\r", "\n"), '', $line))->execute();
            }
            catch (Exception $e) {
                $log_error = new ExceptionsLog();
                $log_error->insert(Exceptions::ERROR,
                    $this->name_shop,
                    'Oshibka v sql zaprose. ' . $this->name_shop . ' .Oshibka: ' . $e->getMessage(),
                    222
                );

                throw new \Exception("Wrong Sql request !!! " . $e->getMessage() . ' . sql file: ' . $sql_file);
            }
        }

        $transaction->commit();
    }

    /**
     * @param $file_path
     * @return \Generator
     */
    private function generator($file_path) {
        $f = fopen($file_path, 'r');
        try {
            while ($line = fgets($f))
                yield $line;
        }
        finally {
            fclose($f);
        }
    }
}
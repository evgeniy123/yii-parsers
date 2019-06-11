<?php

namespace console\repositories\mailRepositorie;

use Yii;
use yii\mail\MailerInterface;


class sendMail
{
    private $mailer;
    private $email_to;

    public function __construct(MailerInterface $mailer)
    {
        //$this->email_to = Yii::$app->settings->getValue('support_email');
        $this->email_to = 'evgeniy123@gmail.com';
        $this->mailer = $mailer;

    }

    /**
     * @param $object
     * @param $message
     */
    public function sendEmailGeneralError($object, $message)
    {
        $sent = $this->mailer
            ->compose(
                [
                    'html' => 'crontab/error_general/error-general-html',
                    'text' => 'crontab/error_general/error-general-txt'
                ],
                [
                    'message' => $message,
                    'object' => $object
                ]
            )
            ->setTo($this->email_to)
            ->setSubject($object)
            ->send();
        if (!$sent) {
            throw new \RuntimeException('Email sending error. ' . $message . ' sendEmailGeneralError');
        }
    }

    /**
     * @param $count_images_copied
     * @param $time_execute_move
     * @param $time_execute_delete
     * @param $total_images
     * @param $count_images_inserted
     */
    public function sendEmailStatistic(
        $count_images_copied,
        $time_execute_move,
        $time_execute_delete,
        $total_images,
        $count_images_inserted
    )
    {
        $sent = $this->mailer
            ->compose(
                [
                    'html' => 'crontab/statistic/statistic-html',
                    'text' => 'crontab/statistic/statistic-txt'
                ],
                [
                    'counter_inserted' => $count_images_inserted,
                    'total' => $total_images,
                    'count_images_copied' => $count_images_copied,
                    'time_execute_move' => $time_execute_move,
                    'time_execute_delete' => $time_execute_delete
                ]
            )
            ->setTo($this->email_to)
            ->setSubject('Statistic ')
            ->send();
        if (!$sent) {
            throw new \RuntimeException('Email sending error. sendEmailStatistic');
        }
    }

    public function sendErrorDb($error, $sql_file)
    {
        $sent = $this->mailer
            ->compose(
                [
                    'html' => 'crontab/error_sql/error-sql-html',
                    'text' => 'crontab/error_sql/error-sql-txt'
                ],
                [
                    'request' => $error,
                    'sql_file' => $sql_file
                ]
            )
            ->setTo($this->email_to)
            ->setSubject('Incorrect Sql  from file ')
            ->send();
        if (!$sent) {
            throw new \RuntimeException('Email sending error. sendErrorDb');
        }
    }

    public function sendErrorMoveFiles($message)
    {
        $sent = $this->mailer
            ->compose(
                [
                    'html' => 'crontab/move_images/error-moving-files-html',
                    'text' => 'crontab/move_images/error-moving-files-txt'
                ],
                [
                    'message' => $message
                ]
            )
            ->setTo($this->email_to)
            ->setSubject('Error moving images')
            ->send();
        if (!$sent) {
            throw new \RuntimeException('Email sending error. sendErrorMoveFiles');
        }
    }

    public function sendEmail($shop, $directory_incorrect)
    {
        $sent = $this->mailer
            ->compose(
                [
                    'html' => 'crontab/warning_not_directory/warning-not-directory-html',
                    'text' => 'crontab/warning_not_directory/warning-not-directory-txt'
                ],
                [
                    'shop' => $shop,
                    'directory_incorrect' => $directory_incorrect,
                ]
            )
            ->setTo($this->email_to)
            ->setSubject('Incorrect directory')
            ->send();
        if (!$sent) {
            throw new \RuntimeException('Email sending error. sendEmail');
        }
    }


    public function sendEmailALotFilesForShop($shop, $number)
    {
        $sent = $this->mailer
            ->compose(
                [
                    'html' => 'crontab/warning_much_in_directory/much-directory-html',
                    'text' => 'crontab/warning_much_in_directory/much-directory-txt'
                ],
                [
                    'shop' => $shop,
                    'number' => $number,
                ]
            )
            ->setTo($this->email_to)
            ->setSubject('Much directory for shop' . $shop)
            ->send();
        if (!$sent) {
            throw new \RuntimeException('Email sending error. sendEmailALotFilesForShop');
        }
    }


}
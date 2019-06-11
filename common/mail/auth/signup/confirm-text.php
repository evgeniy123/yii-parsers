<?php

/* @var $this yii\web\View */

use common\entities\User;

/* @var $user User */

$confirmLink = Yii::$app->urlManager->createAbsoluteUrl(['auth/signup/confirm', 'token' => $user->email_confirm_token]);
?>
Hello <?= $user->name ?>,

Follow the link below to confirm your email:

<?= $confirmLink ?>

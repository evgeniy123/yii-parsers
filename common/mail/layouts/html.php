<?php
use yii\helpers\Html;

/* @var $this \yii\web\View view component instance */
/* @var $message \yii\mail\MessageInterface the message being composed */
/* @var $content string main view render result */
?>
<?php $this->beginPage() ?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>

    <!--[if gte mso 9]>
    <xml>
        <o:OfficeDocumentSettings>
            <o:AllowPNG/>
            <o:PixelsPerInch>96</o:PixelsPerInch>
        </o:OfficeDocumentSettings>
    </xml>
    <![endif]-->
    <meta http-equiv="Content-type" content="text/html; charset=utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="format-detection" content="date=no" />
    <meta name="format-detection" content="address=no" />
    <meta name="format-detection" content="telephone=no" />
    <link href="https://fonts.googleapis.com/css?family=Roboto:400,400i,700,700i" rel="stylesheet" />

    <meta http-equiv="Content-Type" content="text/html; charset=<?= Yii::$app->charset ?>" />
    <title><?= Html::encode($this->title) ?></title>
    <?php $this->head() ?>

    <style type="text/css" media="screen">
        [style*="Roboto"] {
            font-family: 'Roboto', Arial, sans-serif !important
        }

        .text-top a { color:#26252a; text-decoration:none }

        .text-footer a,
        .text-footer-r a { color:#1f1e23; text-decoration:none }

        .text-date2 a { color:#f6de17; text-decoration:none }

        .text-top-white a,
        .text2-center-white a { color:#ffffff; text-decoration:none }

        /* Linked Styles */
        body { padding:0 !important; margin:0 !important; display:block !important; min-width:100% !important; width:100% !important; background:#1f1e23; -webkit-text-size-adjust:none }
        a { color:#26252a; text-decoration:none }
        p { padding:0 !important; margin:0 !important }
        img { -ms-interpolation-mode: bicubic; /* Allow smoother rendering of resized image in Internet Explorer */ }

        /* Mobile styles */
        @media only screen and (max-device-width: 480px), only screen and (max-width: 480px) {
            table[class='mobile-shell'] { width: 100% !important; min-width: 100% !important; }
            table[class='center'] { margin: 0 auto !important; }

            td[class='td'] { width: 100% !important; min-width: 100% !important; }

            div[class='mobile-br-5'] { height: 5px !important; }
            div[class='mobile-br-10'] { height: 10px !important; }
            div[class='mobile-br-15'] { height: 15px !important; }
            div[class='mobile-br-25'] { height: 25px !important; }

            th[class='m-td'],
            td[class='m-td'],
            table[class='m-td'],
            div[class='hide-for-mobile'],
            span[class='hide-for-mobile'] { display: none !important; width: 0 !important; height: 0 !important; font-size: 0 !important; line-height: 0 !important; min-height: 0 !important; }
            td[class='m-auto'] { width: auto !important; }

            span[class='mobile-block'] { display: block !important; }
            td[class='text-top'],
            td[class='text-top-r'],
            td[class='text-top-grey'],
            td[class='text-top-white'],
            div[class='text-1'],
            div[class='text-footer'],
            div[class='text-footer-r'],
            div[class='footer-title'],
            div[class='img-m-center'] { text-align: center !important; }

            div[class='img-m-left'] { text-align: left !important; }

            div[class='fluid-img'] img,
            td[class='fluid-img'] img { width: 100% !important; max-width: 100% !important; height: auto !important; }
            td[class='fluid-img2'] { width: auto !important; }
            td[class='fluid-img2'] img { width: 100% !important; max-width: 100% !important; height: auto !important; }

            th[class='column'],
            th[class='column-top'],
            th[class='column-bottom'],
            th[class='column-dir'] { float: left !important; width: 100% !important; display: block !important; }

            td[class='content-spacing'] { width: 10px !important; }

            td[class='text-2-c'] { width: 20px !important; }
            div[class='text-footer-c'] { font-size: 11px !important; line-height: 20px !important; }
        }
    </style>

</head>
<body>
    <?php $this->beginBody() ?>

    <body class="body" style="padding:0 !important; margin:0 !important; display:block !important; min-width:100% !important; width:100% !important; background:#1f1e23; -webkit-text-size-adjust:none">
    <table width="100%" border="0" cellspacing="0" cellpadding="0" bgcolor="#1f1e23">
        <tr>
            <td align="center" valign="top">

    <?= $this->render("@common/mail/partials/_header");  ?>

    <?= $content ?>

    <?= $this->render("@common/mail/partials/_footer");  ?>

            </td>
        </tr>
    </table>

    <?php $this->endBody() ?>
</body>
</html>
<?php $this->endPage() ?>

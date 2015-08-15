<?php

namespace modules\image\captcha;

use diversen\image\captcha;

class module {

    // -------------------------------------------------------------------
    // captcha.php
    // This file gets the request and initialize the CAPTCHA class
    // Copyright (c) 2005 Gonçalo "gesf" Fontoura.
    // -------------------------------------------------------------------
    public function indexAction() {

        header("Expires: Mon, 23 Jul 1993 05:00:00 GMT");
        header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
        // HTTP/1.1
        header("Cache-Control: no-store, no-cache, must-revalidate");
        header("Cache-Control: post-check=0, pre-check=0", false);
        // HTTP/1.0
        header("Pragma: no-cache");

        $str = $_SESSION['cstr'];
        $captcha = new captcha();
        $captcha->create($str);
        die;
    }

}

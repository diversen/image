<?php

namespace modules\image\captcha;

use Securimage;

class module {

    public function indexAction () {
        $img = new Securimage();
        $img->show();  
        die;
    }
}

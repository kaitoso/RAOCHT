<?php
namespace App\Handler;

use \Eventviva\ImageResize;

class Avatar
{
    public static function generateAvatar($image, $name){
        $image = new ImageResize($image);
        $image->resizeToBestFit(320, 480);
        $image->save(__DIR__. '/../../../public/avatar/b/'.$name.'.png', IMAGETYPE_PNG);
        $image->resize(80,80);
        $image->save(__DIR__. '/../../../public/avatar/s/'.$name.'.png', IMAGETYPE_PNG);
    }
}

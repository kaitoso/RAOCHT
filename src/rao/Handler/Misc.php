<?php
namespace App\Handler;
use Eventviva\ImageResize;

/**
 *
 */
class Misc
{
    public static function mempty()
    {
        foreach (func_get_args() as $arg) {
            if (empty($arg)) {
                return true;
            }

        }
        return false;
    }

    public static function removeAccent(string $str){
        $unwanted_array = array(
            'Š'=>'S', 'š'=>'s', 'Ž'=>'Z', 'ž'=>'z', 'À'=>'A', 'Á'=>'A', 'Â'=>'A', 'Ã'=>'A', 'Ä'=>'A',
            'Å'=>'A', 'Æ'=>'A', 'Ç'=>'C', 'È'=>'E', 'É'=>'E', 'Ê'=>'E', 'Ë'=>'E', 'Ì'=>'I', 'Í'=>'I',
            'Î'=>'I', 'Ï'=>'I', 'Ñ'=>'N', 'Ò'=>'O', 'Ó'=>'O', 'Ô'=>'O', 'Õ'=>'O', 'Ö'=>'O', 'Ø'=>'O',
            'Ù'=>'U', 'Ú'=>'U', 'Û'=>'U', 'Ü'=>'U', 'Ý'=>'Y', 'Þ'=>'B', 'ß'=>'Ss', 'à'=>'a', 'á'=>'a',
            'â'=>'a', 'ã'=>'a', 'ä'=>'a', 'å'=>'a', 'æ'=>'a', 'ç'=>'c', 'è'=>'e', 'é'=>'e', 'ê'=>'e',
            'ë'=>'e', 'ì'=>'i', 'í'=>'i', 'î'=>'i', 'ï'=>'i', 'ð'=>'o', 'ñ'=>'n', 'ò'=>'o', 'ó'=>'o',
            'ô'=>'o', 'õ'=>'o', 'ö'=>'o', 'ø'=>'o', 'ù'=>'u', 'ú'=>'u', 'û'=>'u', 'ý'=>'y', 'þ'=>'b',
            'ÿ'=>'y'
        );
        return strtr($str, $unwanted_array );
    }

    public static function convertOrdinalToNumber(string $str)
    {
        if($str === 'primero' || $str === 'uno' )
            return 1;
        if($str === 'primer')
            return 1;
        if($str === 'segundo' || $str === 'dos')
            return 2;
        if($str === 'tercero' || $str === 'tres')
            return 3;
        if($str === 'cuarto' || $str === 'cuatro')
            return 4;
        if($str === 'quinto' || $str === 'cinco')
            return 5;
        if($str === 'sexto' || $str === 'seis')
            return 6;
        if($str === 'septimo' || $str === 'siete')
            return 7;
        if($str === 'octavo' || $str === 'ocho')
            return 8;
        if($str === 'noveno' || $str === 'nueve')
            return 9;
        if($str === 'decimo' || $str === 'diez')
            return 10;
    }
}

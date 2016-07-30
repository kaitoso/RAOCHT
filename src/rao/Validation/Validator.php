<?php
namespace App\Validation;

use Respect\Validation\Validator as Respect;
use Respect\Validation\Exceptions\NestedValidationException;

class Validator
{
    protected $errors;
    protected $container;

    /**
     * Validator constructor.
     * @param $container
     */
    public function __construct($container)
    {
        $this->container = $container;
    }


    public function validate($request, array $rules){
        foreach ($rules as $field => $rule){
            try {
                $rule->assert($request->getParam($field));
                //$rule->setName(ucfirst($field))->assert($request->getParam($field));
            }catch (NestedValidationException $e){
                $e->setParam('translator', array($this, 'customError'));
                $this->errors[$field] = $e->getMessages();
            }
        }
        $this->container->session->set('errors', $this->errors);
        return $this;
    }

    public function validateArgs($args, array $rules)
    {
        foreach ($rules as $field => $rule){
            try {
                $rule->assert($args[$field]);
                //$rule->setName(ucfirst($field))->assert($request->getParam($field));
            }catch (NestedValidationException $e){
                $e->setParam('translator', array($this, 'customError'));
                $this->errors[$field] = $e->getMessages();
            }
        }
        $this->container->session->set('errors', $this->errors);
        return $this;
    }

    public function customError( $str )
    {
        if($str == '{{name}} must not be empty'){
            return 'Este campo no debe estar vacío';
        }else if($str == '{{name}} must contain only letters (a-z)') {
            return 'Este campo debe contener solo letras (a-z)';
        }else if($str == '{{name}} must contain only letters (a-z) and digits (0-9)'){
            return 'Este campo solo debe contener letras (a-z) y digitos (0-9)';
        }else if($str == '{{name}} must contain only letters (a-z) and "{{additionalChars}}"') {
            return 'Este campo debe contener solo letras (a-z) y los caracteres "{{additionalChars}}"';
        }else if($str == '{{name}}  must contain only letters (a-z), digits (0-9) and "{{additionalChars}}"'){
            return 'Este campo debe contener sólo letras (a-z), digitos (0-9) y los caracteres {{additionalChars}}';
        }else if($str == '{{name}} must be a boolean value'){
            return 'Este campo debe ser un valor booleano';
        }else if($str == 'No items were found for key chain {{name}}'){
            return 'No se encontró la llave del elemento {{name}}';
        }else if($str == '{{name}} must have a length between {{minValue}} and {{maxValue}}') {
            return 'Este campo debe contener una longitud minima de {{minValue}} y máxima de {{maxValue}}';
        }else if($str == '{{name}} must have a length greater than {{minValue}}'){
            return 'Este campo debe tener una longitud mínima de {{minValue}}';
        }else if($str == '{{name}} must be a hex RGB color'){
            return 'Este campo debe tener un color haxadecimal (RGB)';
        }
        return $str;
    }

    public function failed()
    {
        return !empty($this->errors);
    }
}
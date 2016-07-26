<?php
namespace App\Controller;

use App\Handler\Misc;
use NumberFormatter;
use Psr\Http\Message\RequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Respect\Validation\Exceptions\NestedValidationException;
use Slim\Container;
use Respect\Validation\Validator as v;
class SearchController extends BaseController{

    /**
     * SearchController constructor.
     * @param Container $c
     */
    public function __construct(Container $c)
    {
        parent::__construct($c);
    }

    public function getTitle(Request $request, Response $response, $args)
    {
        $query = Misc::removeAccent(strtolower($args['query']));
        $validation = v::notEmpty()->alnum('.-/áéíóú');
        try{
            $validation->assert($query);
        }catch (NestedValidationException $e){
            $e->setParam('translator', array(new \App\Validation\Validator(null), 'customError'));
            return $response->withJson($e->getMessages());
        }
        $search = "//title[contains(translate(@name, 'ABCDEFGHJIKLMNOPQRSTUVWXYZáéíóú', 'abcdefghjiklmnopqrstuvwxyzaeiou'),'" . $query ."')]";
        $input = file_get_contents(__DIR__ . '/../Storage/constitucion.xml');
        $xml = simplexml_load_string($input);
        $result = $xml->xpath($search)[0];
        $current = [
            'name' => (string) $result['name'],
            'desc' => (string) $result['desc']
        ];
        foreach ($result->children() as $item){
            if($item->getName() === 'chapter') {
                $currentChapter = [
                    'id' => (int)$item['id'],
                    'desc' => (string)$item['desc']
                ];

                foreach ($item->xpath('descendant::article') as $article) {
                    $par = (array) $article->children();
                    $paragraphs = $par['paragraph'];
                    $currentChapter['articles'][] = [
                        'id' => (int)$article['id'],
                        'text' => is_array($paragraphs) ? array_map(function ($str) {
                            return $str;
                        }, $paragraphs) : [$paragraphs]
                    ];
                };
                //var_dump($currentChapter);
                $current['chapters'][] = $currentChapter;
            }else if($item->getName() === 'article'){
                $par = (array) $item->children();
                $paragraphs = $par['paragraph'];
                $current['articles'][] = [
                    'id' => (int) $item['id'],
                    'text' => is_array($paragraphs) ? array_map(function ($str) {
                        return $str;
                    }, $paragraphs) : [$paragraphs]
                ];
            }
        }
        //var_dump($current);
        return $this->view->render($response, 'lista.twig', $current);
    }

    public function getChapter(Request $request, Response $response, $args)
    {
        $title = Misc::removeAccent(strtolower($args['title']));
        $validateTitle = v::notEmpty()->alnum('.-/áéíóú');
        $validateChapter = v::notEmpty()->intVal();
        try{
            $validateTitle->assert($title);
            $validateChapter->assert($args['id']);
        }catch (NestedValidationException $e){
            $e->setParam('translator', array(new \App\Validation\Validator(null), 'customError'));
            return $response->withJson($e->getMessages());
        }
        $search = "//title[translate(@name, 'ABCDEFGHJIKLMNOPQRSTUVWXYZáéíóú', 'abcdefghjiklmnopqrstuvwxyzaeiou')='{$title}']/chapter[@id='{$args['id']}']";
        $input = file_get_contents(__DIR__ . '/../Storage/constitucion.xml');
        $xml = simplexml_load_string($input);
        $result = $xml->xpath($search)[0];
        $title = $result->xpath("parent::title")[0];
        $jsonResult = [
            'id' => (int) $result['id'],
            'desc' => (string) $result['desc'],
            'title' => [
                'name' => (string) $title['name'],
                'desc' => (string) $title['desc']
            ]
        ];
        foreach ($result->xpath('descendant::article') as $article) {
            $par = (array) $article->children();
            $paragraphs = $par['paragraph'];
            $jsonResult['articles'][] = [
                'id' => (int)$article['id'],
                'text' => is_array($paragraphs) ? array_map(function ($str) {
                    return $str;
                }, $paragraphs) : [$paragraphs]
            ];
        };
        return $this->view->render($response, 'capitulo.twig', $jsonResult);
    }

    public function getArticle(Request $request, Response $response, $args)
    {
        $query = Misc::removeAccent(strtolower($args['query']));
        $validation = v::notEmpty()->intVal();
        try{
            $validation->assert($query);
        }catch (NestedValidationException $e){
            $e->setParam('translator', array(new \App\Validation\Validator(null), 'customError'));
            return $response->withJson($e->getMessages());
        }
        $search = "//article[@id='" . $query ."']";
        $input = file_get_contents(__DIR__ . '/../Storage/constitucion.xml');
        $xml = simplexml_load_string($input);
        $result = $xml->xpath($search)[0];
        $parrafos = (array) $result->children();
        $jsonResult = [
            'id' => (int) $result['id'],
            'text' => is_array($parrafos['paragraph']) ? $parrafos['paragraph'] : [$parrafos['paragraph']]
        ];
        //var_dump($jsonResult);
        return $this->view->render($response, 'articulo.twig', $jsonResult);
    }

     public function getArticles(Request $request, Response $response, $args)
    {
        $input = file_get_contents(__DIR__ . '/../Storage/constitucion.xml');
        $xml = simplexml_load_string($input);
        $result = $xml->xpath('//article');
        $jsonResult = [];
        foreach ($result as $index => $item){
            $text = array();
            foreach ($item->children() as $child){
                $text[] = (string) $child;
            }
            $jsonResult[] = [
                'id' => (int) $item['id'],
                'text' => $text
            ];
        }
        return $response->withJson($jsonResult);
    }


    public function postFilterSearch(Request $request, Response $response)
    {
        $validation = $this->validator->validate($request, [
            'busqueda' => v::notEmpty()->alnum('.-/áéíóú'),
            'filter' => v::arrayType()
                ->keyNested('articulos',  v::notBlank()->boolVal())
                ->keyNested('capitulos',  v::notBlank()->boolVal())
                ->keyNested('titulos',  v::notBlank()->boolVal())
        ]);
        if($validation->failed()){
            return $response->withJson($this->session->get('errors'));
        }
        $search = [];
        $limit = true;
        $query = Misc::removeAccent(strtolower($request->getParam('busqueda')));
        $filters = $request->getParam('filter');
        $xml = simplexml_load_string(file_get_contents(__DIR__ . '/../Storage/constitucion.xml'));
        // Comprobar si hay una búsqueda definida
        if(preg_match('/^((articulo [0-9]{1,3})|([0-9]{1,3}))$/', $query)) {
            preg_match('/(\d+)/', $query, $matches);
            $search[] = "//article[@id='{$matches[0]}']/paragraph";
            $limit = false;
        }
        if(preg_match('/^(titulo|((primer|segund|tercer|cuart|quint|sext|octav|noven)(o)?))'
            .'\s((\d+)|((primer|segund|tercer|cuart|quint|sext|octav|noven)o)|titulo)$/', $query))
        {
            $id = 'titulo ';
            preg_match('/((primer|segund|tercer|cuart|quint|sext|octav|noven)(o)?)|(\d+)|'
            .'(uno|dos|tres|cuatro|cinco|seis|siete|ocho|nueve)/', $query, $matches);
            if(ctype_digit($matches[0])){
                $nf = new NumberFormatter('es_ES', NumberFormatter::SPELLOUT);
                $nf->setTextAttribute(NumberFormatter::DEFAULT_RULESET,
                    "%spellout-ordinal-masculine");
                $id .= $nf->format($matches[0]);
            }else if($matches[0] === 'primer'){
                $id .= 'primero';
            }else{
                $id.= $matches[0];
            }
            $search[] = "//title[contains(translate(@name, 'ABCDEFGHJIKLMNOPQRSTUVWXYZáéíóú', 'abcdefghjiklmnopqrstuvwxyzaeiou'),'" . $id ."')]";
        }
        if(preg_match('/^(capitulo|((primer|segund|tercer|cuart|quint|sext|octav|noven)(o)?))'
            .'\s((\d+)|((primer|segund|tercer|cuart|quint|sext|octav|noven)o)|capitulo|'
            .'(uno|dos|tres|cuatro|cinco|seis|siete|ocho|nueve))$/', $query))
        {
            $id = 0;
            preg_match('/((primer|segund|tercer|cuart|quint|sext|octav|noven)(o)?)|(\d+)|'
                .'(uno|dos|tres|cuatro|cinco|seis|siete|ocho|nueve)/', $query, $matches);
            if(ctype_digit($matches[0])){
                $id = $matches[0];
            }else {
                $id = Misc::convertOrdinalToNumber($matches[0]);
            }
            $search[] = "//chapter[@id='" . $id ."']";
        }
        // Comprobar si hay filtros especificados
        if($filters['titulos'] === 'true'){
            $search[] = "//title[contains(translate(@name, 'ABCDEFGHJIKLMNOPQRSTUVWXYZáéíóú', 'abcdefghjiklmnopqrstuvwxyzaeiou'),'" . $query ."')]";
            $search[] = "//title[contains(translate(@desc, 'ABCDEFGHJIKLMNOPQRSTUVWXYZáéíóú', 'abcdefghjiklmnopqrstuvwxyzaeiou'),'" . $query ."')]";
        }
        if($filters['capitulos'] === 'true'){
            $search[] = "//chapter[contains(translate(@desc, 'ABCDEFGHJIKLMNOPQRSTUVWXYZáéíóú', 'abcdefghjiklmnopqrstuvwxyzaeiou'),'" . $query ."')]";
        }
        if($filters['articulos'] === 'true'){
            $search[] = "//paragraph[contains(translate(text(), 'ABCDEFGHJIKLMNOPQRSTUVWXYZáéíóú', 'abcdefghjiklmnopqrstuvwxyzaeiou'),'" . $query ."')]";
        }
        // No filtros especificados
        if(count($search) === 0){
            $search[] = "//*[contains(translate(@name, 'ABCDEFGHJIKLMNOPQRSTUVWXYZáéíóú', 'abcdefghjiklmnopqrstuvwxyzaeiou'),'" . $query ."')]";
            $search[] = "//*[contains(translate(@desc, 'ABCDEFGHJIKLMNOPQRSTUVWXYZáéíóú', 'abcdefghjiklmnopqrstuvwxyzaeiou'),'" . $query ."')]";
            $search[] = "//*[contains(translate(text(), 'ABCDEFGHJIKLMNOPQRSTUVWXYZáéíóú', 'abcdefghjiklmnopqrstuvwxyzaeiou'),'" . $query ."')]";
        }
        $result = $xml->xpath(join($search, ' | '));
        $jsonResult = array();
        foreach ($result as $index => $item) {
            if($item->getName() === "title"){
                $firstArticle = $item->xpath('descendant::article[1]')[0];
                $firstParagraph = $firstArticle->xpath('descendant::paragraph[1]')[0];
                $lastArticle = $item->xpath('descendant::article[last()]')[0];
                $jsonResult['title'][] = [
                    'first' => (int) $firstArticle['id'],
                    'firstText' => ['Artículo '.(int) $firstArticle['id']. '. '.(string) $firstParagraph],
                    'last' => (int) $lastArticle['id'],
                    'type' => 'title',
                    'name' => (string) $item['name'],
                    'desc' => (string) $item['desc']
                ];
            }
            if($item->getName() === "chapter"){
                $title = $item->xpath("parent::title")[0];
                $firstArticle = $item->xpath('descendant::article[1]')[0];
                $paragraph = $firstArticle->xpath('descendant::paragraph[position() < 3]');
                $jsonResult['chapter'][] = [
                    'type' => 'chapter',
                    'title' => (string) $title['name'],
                    'titleDesc' => (string) $title['desc'],
                    'first' => (int) $firstArticle['id'],
                    'firstText' => array_map(function($str){ return (string) $str; }, (array) $paragraph),
                    'id' => (string) $item['id'],
                    'desc' => (string ) $item['desc']
                ];
            }
            if($item->getName() === "paragraph"){
                $article = $item->xpath("parent::article")[0];
                $id = (int) $article['id'];
                $texto = (string) $item;
                if(empty($jsonResult['article'][$id])){
                    $jsonResult['article'][$id] = [
                        'text' => [($limit) ? mb_substr($texto, 0, 200). '...' : $texto]
                    ];
                }else{
                    $jsonResult['article'][$id]['text'][] = ($limit) ? mb_substr($texto, 0, 200). '...' : $texto;
                }
            }
        }
        return $response->withJson($jsonResult);
    }
    
}
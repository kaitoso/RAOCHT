<?php
if (PHP_SAPI != 'cli') {
    echo "Este instalador debe iniciar en modo consola.\n";
    return false;
}
require __DIR__ . '/vendor/autoload.php';
$settings = require __DIR__ . '/src/settings.php';
echo "[+] Comprobando la conexión con la base de datos...\n";
$capsule = new \Illuminate\Database\Capsule\Manager;
$capsule->addConnection($settings['settings']['database']);
$capsule->setAsGlobal();
$capsule->bootEloquent();
$capsule::connection()->enableQueryLog();
try{
    echo "[~] Versión de la base de datos: " . $capsule::select('SELECT @@version as version')[0]->version. "\n";
    echo "[~] Conexión realizada con éxito... Procediendo a instalar base de datos\n";
    $purge = new \App\Database\AppSchemas();
    echo "[-] Quitando tablas...\n";
    $purge->down();
    echo "[-] Insertando el esquema de las tablas...\n";
    $purge->up();
    echo "[-] Insertando los datos...\n";
    $purge->seed();
    echo "[+] Instalación completa.\n";
}catch(\Exception $e){
    echo $e->getTraceAsString();
    var_dump($capsule::connection()->getQueryLog());
}
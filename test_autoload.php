<?php
require 'vendor/autoload.php';

var_dump(class_exists('App\Models\Sigi\Permiso'));
if (class_exists('App\Models\Sigi\Permiso')) {
    $p = new App\Models\Sigi\Permiso();
    echo "¡Instancia creada!\n";
} else {
    echo "NO ENCONTRADO\n";
}

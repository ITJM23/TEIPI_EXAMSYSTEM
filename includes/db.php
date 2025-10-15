<?php

// ============ Teipi_emp3 Database ============
$serverName3 = "LAP0117";
$connectionOptions3 = array(
    "Database" => "teipiexam",
    "Uid" => "sa",
    "PWD" => "IS@Admin"
);

$con3 = sqlsrv_connect($serverName3, $connectionOptions3);

if (!$con3) {
    die(print_r(sqlsrv_errors(), true));
} else {
    //echo 'Connect success to Teipi_emp3 database';
}


?>

<?php

// ============ Teipi_emp3 Database ============
$serverName2 = "LAP0117";
$connectionOptions2 = array(
    "Database" => "eims",
    "Uid" => "sa",
    "PWD" => "IS@Admin"
);

$con2 = sqlsrv_connect($serverName2, $connectionOptions2);

if (!$con2) {
    die(print_r(sqlsrv_errors(), true));
} else {
    //echo 'Connect success to Teipi_emp3 database';
}


?>
<?php

$conex = mysqli_connect("localhost", "root", "", "foro");

if (!$conex) {
    die("Error de conexión: " . mysqli_connect_error());
}

mysqli_set_charset($conex, "utf8mb4");

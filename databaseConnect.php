<?php
    
    $mysqli = new mysqli('localhost', 'user', 'password', 'database');
    
    if($mysqli->connect_errno) {
        die($mysqli->connect_errno . ': ' . $mysqli->connect_error);
    }
    
?>